<?php

namespace App\Services\Auth;

use App\Models\PasswordResetToken;
use App\Models\User;
use App\Notifications\PasswordResetCodeNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Fluxo próprio de reset de senha por código de 6 dígitos (não usa o
 * `Illuminate\Auth\Passwords\PasswordBroker` nativo — o front espera um
 * wizard de 3 passos com código digitado, não um link de e-mail).
 */
class PasswordResetService
{
    private const CODE_TTL_MINUTES = 15;

    public function __construct(private readonly TokenService $tokens) {}

    /**
     * Gera e envia um código novo, se o e-mail pertencer a um usuário.
     * Não revela ao chamador se o usuário existe (resposta do controller é
     * sempre genérica) — evita user enumeration.
     */
    public function requestCode(string $email): void
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            return;
        }

        PasswordResetToken::where('email', $email)->delete();

        $code = (string) random_int(100000, 999999);

        PasswordResetToken::create([
            'email' => $email,
            'token' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
        ]);

        $user->notify(new PasswordResetCodeNotification($code));
    }

    /**
     * Confere o código sem consumi-lo (usado no passo 2→3 do wizard, só
     * pra validar antes de deixar o usuário digitar a nova senha).
     *
     * @throws ValidationException Código inválido ou expirado.
     */
    public function verifyCode(string $email, string $code): void
    {
        $this->findValidToken($email, $code);
    }

    /**
     * Confere o código de novo, troca a senha, consome o código e revoga
     * todas as sessões (access+refresh) do usuário em todos os dispositivos.
     *
     * @throws ValidationException Código inválido ou expirado.
     */
    public function reset(string $email, string $code, string $password): void
    {
        $this->findValidToken($email, $code);

        $user = User::where('email', $email)->firstOrFail();
        $user->forceFill(['password' => $password])->save();

        PasswordResetToken::where('email', $email)->delete();

        $this->tokens->revokeAllSessionsFor($user);
    }

    private function findValidToken(string $email, string $code): PasswordResetToken
    {
        $record = PasswordResetToken::where('email', $email)
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $record || ! Hash::check($code, $record->token)) {
            throw ValidationException::withMessages([
                'code' => ['Código inválido ou expirado.'],
            ]);
        }

        return $record;
    }
}
