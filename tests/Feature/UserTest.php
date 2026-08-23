<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    private function loginTokens(): array
    {
        User::factory()->create([
            'email' => 'me@example.com',
            'password' => Hash::make('senha-correta'),
        ]);

        return $this->postJson('/api/login', [
            'email' => 'me@example.com',
            'password' => 'senha-correta',
        ])->json();
    }

    public function test_returns_the_authenticated_user(): void
    {
        $login = $this->loginTokens();

        $this->withHeader('Authorization', 'Bearer '.$login['access_token'])
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonStructure(['id', 'name', 'email', 'role', 'created_at'])
            ->assertJsonPath('email', 'me@example.com');
    }

    public function test_missing_token_returns_clean_unauthorized_json_not_a_redirect(): void
    {
        $response = $this->getJson('/api/user');

        // Backend é API-only: guest sem token nunca deve ser redirecionado
        // pra uma rota "login" web — sempre 401 JSON limpo.
        $response->assertUnauthorized()
            ->assertHeader('content-type', 'application/json')
            ->assertJson(['message' => 'Unauthenticated.']);

        $this->assertFalse($response->headers->has('Location'));
    }

    public function test_invalid_token_is_rejected(): void
    {
        $this->withHeader('Authorization', 'Bearer token-que-nao-existe')
            ->getJson('/api/user')
            ->assertUnauthorized();
    }

    public function test_expired_access_token_is_rejected(): void
    {
        $login = $this->loginTokens();

        // Access token tem TTL de 2h — simula ele já vencido direto no
        // banco (sem esperar 2h de verdade).
        PersonalAccessToken::findToken($login['access_token'])
            ->update(['expires_at' => now()->subMinute()]);

        $this->withHeader('Authorization', 'Bearer '.$login['access_token'])
            ->getJson('/api/user')
            ->assertUnauthorized();
    }
}
