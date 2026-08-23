<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(private readonly TokenService $tokens) {}

    /**
     * Confere e-mail + senha e emite um par novo de tokens.
     *
     * @return array{user: User, access_token: string, access_token_expires_at: Carbon, refresh_token: string, refresh_token_expires_at: Carbon}
     *
     * @throws ValidationException Mensagem genérica — não revela se o e-mail existe.
     */
    public function attempt(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();

        // Mensagem genérica também pro caso de conta desativada — não revela
        // pra quem tenta logar se a conta existe/está desativada.
        if (! $user || ! Hash::check($password, $user->password) || ! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['E-mail ou senha inválidos.'],
            ]);
        }

        return ['user' => $user, ...$this->tokens->issuePairFor($user)];
    }
}
