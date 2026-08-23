<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    private function loginTokens(): array
    {
        User::factory()->create([
            'email' => 'logout@example.com',
            'password' => Hash::make('senha-correta'),
        ]);

        return $this->postJson('/api/login', [
            'email' => 'logout@example.com',
            'password' => 'senha-correta',
        ])->json();
    }

    public function test_logs_out_with_valid_access_token(): void
    {
        $login = $this->loginTokens();

        $this->withHeader('Authorization', 'Bearer '.$login['access_token'])
            ->postJson('/api/logout')
            ->assertNoContent();
    }

    public function test_missing_token_returns_clean_unauthorized_json(): void
    {
        $response = $this->postJson('/api/logout');

        $response->assertUnauthorized()
            ->assertHeader('content-type', 'application/json')
            ->assertJson(['message' => 'Unauthenticated.']);

        $this->assertFalse($response->headers->has('Location'));
    }

    public function test_refresh_token_cannot_logout(): void
    {
        $login = $this->loginTokens();

        // Ability `refresh` não autoriza rotas normais — só `/api/refresh`.
        $this->withHeader('Authorization', 'Bearer '.$login['refresh_token'])
            ->postJson('/api/logout')
            ->assertForbidden();
    }

    public function test_invalid_token_is_rejected(): void
    {
        $this->withHeader('Authorization', 'Bearer token-que-nao-existe')
            ->postJson('/api/logout')
            ->assertUnauthorized();
    }
}
