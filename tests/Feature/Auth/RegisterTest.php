<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_registers_and_logs_in_automatically(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Fulano de Tal',
            'email' => 'fulano@example.com',
            'password' => 'segredo6',
            'password_confirmation' => 'segredo6',
        ]);

        $response->assertCreated()->assertJsonStructure([
            'user' => ['id', 'name', 'email', 'role', 'created_at'],
            'token_type',
            'access_token',
            'access_token_expires_at',
            'refresh_token',
            'refresh_token_expires_at',
        ]);

        // Role tem que vir ADMINISTRADOR já na resposta (em memória), não só
        // depois de um reload do banco — regressão do bug em que
        // User::create() não refazia o SELECT após o insert.
        $response->assertJsonPath('user.role', User::ROLE_ADMINISTRADOR);

        $this->assertDatabaseHas('users', [
            'email' => 'fulano@example.com',
            'role' => 'ADMINISTRADOR',
        ]);

        $user = User::where('email', 'fulano@example.com')->firstOrFail();
        $this->assertNotSame('segredo6', $user->password);
        $this->assertSame(User::ROLE_ADMINISTRADOR, $user->role);
    }

    public function test_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'ja-existe@example.com']);

        $response = $this->postJson('/api/register', [
            'name' => 'Outro',
            'email' => 'ja-existe@example.com',
            'password' => 'segredo6',
            'password_confirmation' => 'segredo6',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_rejects_password_confirmation_mismatch(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Fulano',
            'email' => 'fulano2@example.com',
            'password' => 'segredo6',
            'password_confirmation' => 'outra-coisa',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_rejects_missing_required_fields(): void
    {
        $response = $this->postJson('/api/register', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_rejects_password_below_minimum_length(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Fulano',
            'email' => 'fulano3@example.com',
            'password' => 'abc12',
            'password_confirmation' => 'abc12',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_rejects_malformed_email(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Fulano',
            'email' => 'nao-e-um-email',
            'password' => 'segredo6',
            'password_confirmation' => 'segredo6',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('email');
    }
}
