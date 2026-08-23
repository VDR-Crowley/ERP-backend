<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Carbon;

class RegistrationService
{
    public function __construct(private readonly TokenService $tokens) {}

    /**
     * Cria o usuário com role ADMINISTRADOR (nunca vem do request — role não
     * é um campo de RegisterRequest) e já emite um par de tokens (login
     * automático, sem etapa de confirmação de e-mail).
     *
     * Role setado explicitamente aqui (não só confiando no default da coluna
     * `role` na migration): `User::create()` não re-busca do banco depois do
     * insert, então um default só de schema deixa o objeto em memória (e a
     * resposta da API) com `role` null mesmo com a linha correta no banco.
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
            'role' => User::ROLE_ADMINISTRADOR,
            'is_active' => true,
        ]);

        return ['user' => $user, ...$this->tokens->issuePairFor($user)];
    }
}
