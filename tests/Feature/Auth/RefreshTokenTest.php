<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class RefreshTokenTest extends TestCase
{
    use RefreshDatabase;

    private function loginTokens(): array
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('senha-correta'),
        ]);

        return $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'password' => 'senha-correta',
        ])->json();
    }

    public function test_refresh_rotates_the_token_pair(): void
    {
        $login = $this->loginTokens();

        $response = $this->withHeader('Authorization', 'Bearer '.$login['refresh_token'])
            ->postJson('/api/refresh');

        $response->assertOk()->assertJsonStructure([
            'user' => ['id', 'email'],
            'access_token',
            'refresh_token',
        ]);

        $new = $response->json();
        $this->assertNotSame($login['access_token'], $new['access_token']);
        $this->assertNotSame($login['refresh_token'], $new['refresh_token']);
    }

    public function test_old_refresh_token_cannot_be_reused_after_rotation(): void
    {
        $login = $this->loginTokens();

        $this->withHeader('Authorization', 'Bearer '.$login['refresh_token'])
            ->postJson('/api/refresh')
            ->assertOk();

        // RequestGuard memoiza o usuário resolvido por requisição simulada;
        // limpa entre chamadas com tokens diferentes dentro do mesmo teste.
        $this->app['auth']->forgetGuards();

        // Reuso do refresh token antigo tem que falhar (rotação, não reuso).
        $this->withHeader('Authorization', 'Bearer '.$login['refresh_token'])
            ->postJson('/api/refresh')
            ->assertUnauthorized();
    }

    public function test_old_access_token_stops_working_after_rotation(): void
    {
        $login = $this->loginTokens();

        $this->withHeader('Authorization', 'Bearer '.$login['refresh_token'])
            ->postJson('/api/refresh')
            ->assertOk();

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$login['access_token'])
            ->getJson('/api/user')
            ->assertUnauthorized();
    }

    public function test_access_token_cannot_be_used_to_refresh(): void
    {
        $login = $this->loginTokens();

        $this->withHeader('Authorization', 'Bearer '.$login['access_token'])
            ->postJson('/api/refresh')
            ->assertForbidden();
    }

    public function test_missing_token_returns_clean_unauthorized_json(): void
    {
        $response = $this->postJson('/api/refresh');

        $response->assertUnauthorized()
            ->assertHeader('content-type', 'application/json')
            ->assertJson(['message' => 'Unauthenticated.']);

        $this->assertFalse($response->headers->has('Location'));
    }

    public function test_expired_refresh_token_is_rejected(): void
    {
        $login = $this->loginTokens();

        // Refresh token válido tem TTL de 30 dias — simula ele já vencido
        // direto no banco (sem esperar 30 dias de verdade).
        PersonalAccessToken::findToken($login['refresh_token'])
            ->update(['expires_at' => now()->subMinute()]);

        $this->withHeader('Authorization', 'Bearer '.$login['refresh_token'])
            ->postJson('/api/refresh')
            ->assertUnauthorized();
    }
}
