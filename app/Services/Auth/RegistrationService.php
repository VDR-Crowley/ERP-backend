<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Carbon;

class RegistrationService
{
    public function __construct(private readonly TokenService $tokens) {}

    /**
     * Cria o usuário (role fica no valor default da coluna — nunca vem do
     * request) e já emite um par de tokens (login automático, sem etapa de
     * confirmação de e-mail).
     *
     * @param  array{name: string, email: string, password: string}  $data
     * @return array{user: User, access_token: string, access_token_expires_at: Carbon, refresh_token: string, refresh_token_expires_at: Carbon}
     */
    public function register(array $data): array
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        return ['user' => $user, ...$this->tokens->issuePairFor($user)];
    }
}
