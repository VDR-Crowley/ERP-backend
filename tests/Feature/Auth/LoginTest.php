<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_logs_in_with_valid_credentials(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('senha-correta'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'password' => 'senha-correta',
        ]);

        $response->assertOk()->assertJsonStructure([
            'user' => ['id', 'name', 'email', 'role'],
            'access_token',
            'refresh_token',
        ]);
    }

    public function test_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('senha-correta'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'password' => 'senha-errada',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_rejects_unknown_email_with_same_generic_message(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'nao-existe@example.com',
            'password' => 'qualquer',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_rejects_missing_email(): void
    {
        $response = $this->postJson('/api/login', [
            'password' => 'qualquer',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_rejects_missing_password(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'user@example.com',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_rejects_malformed_email(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'nao-e-um-email',
            'password' => 'qualquer',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_access_token_authenticates_protected_route(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('senha-correta'),
        ]);

        $login = $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'password' => 'senha-correta',
        ])->json();

        $this->withHeader('Authorization', 'Bearer '.$login['access_token'])
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('email', 'user@example.com');
    }

    public function test_refresh_token_cannot_access_protected_routes(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('senha-correta'),
        ]);

        $login = $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'password' => 'senha-correta',
        ])->json();

        $this->withHeader('Authorization', 'Bearer '.$login['refresh_token'])
            ->getJson('/api/user')
            ->assertForbidden();
    }

    public function test_logout_revokes_both_tokens_of_the_session(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('senha-correta'),
        ]);

        $login = $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'password' => 'senha-correta',
        ])->json();

        $this->withHeader('Authorization', 'Bearer '.$login['access_token'])
            ->postJson('/api/logout')
            ->assertNoContent();

        // O guard `sanctum` (RequestGuard) memoiza o usuário resolvido na
        // primeira autenticação da requisição e não invalida esse cache
        // sozinho entre chamadas simuladas dentro do mesmo teste — precisa
        // ser limpo manualmente pra cada chamada HTTP seguinte enxergar o
        // estado atual do token no banco.
        $this->app['auth']->forgetGuards();

        // Access token revogado.
        $this->withHeader('Authorization', 'Bearer '.$login['access_token'])
            ->getJson('/api/user')
            ->assertUnauthorized();

        $this->app['auth']->forgetGuards();

        // Refresh token do mesmo par também revogado.
        $this->withHeader('Authorization', 'Bearer '.$login['refresh_token'])
            ->postJson('/api/refresh')
            ->assertUnauthorized();
    }
}
