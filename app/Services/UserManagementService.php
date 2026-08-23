<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Administração de usuários por um ADMINISTRADOR logado (tela de admin do
 * front) — diferente do cadastro público (`/register`, ver RegistrationService).
 * Nunca apaga usuário de verdade: "excluir" é desativar (`is_active=false`).
 */
class UserManagementService
{
    /**
     * @return Collection<int, User>
     */
    public function list(): Collection
    {
        return User::all();
    }

    /**
     * Role nunca vem do request — sempre ADMINISTRADOR (único role hoje).
     * `role` e `is_active` setados explicitamente (não só confiando no
     * default da coluna): `User::create()` não re-busca do banco depois do
     * insert, então um default só de schema deixa o objeto em memória (e a
     * resposta da API) com o campo null mesmo com a linha correta no banco.
     *
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function create(array $data): User
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => User::ROLE_ADMINISTRADOR,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array{name?: string, email?: string, password?: string, is_active?: bool}  $data
     *
     * @throws ValidationException Tentativa de desativar a própria conta.
     */
    public function update(User $user, array $data, User $actingUser): User
    {
        $this->assertNotSelfDeactivation($user, $data, $actingUser);

        $user->update($data);

        return $user;
    }

    /**
     * @throws ValidationException Tentativa de desativar a própria conta.
     */
    public function deactivate(User $user, User $actingUser): void
    {
        $this->assertNotSelfDeactivation($user, ['is_active' => false], $actingUser);

        $user->update(['is_active' => false]);
    }

    private function assertNotSelfDeactivation(User $user, array $data, User $actingUser): void
    {
        if (($data['is_active'] ?? null) === false && $user->is($actingUser)) {
            throw ValidationException::withMessages([
                'is_active' => 'Você não pode desativar a própria conta.',
            ]);
        }
    }
}
