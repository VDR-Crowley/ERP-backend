<?php

namespace Tests\Feature\Auth;

use App\Models\PasswordResetToken;
use App\Models\User;
use App\Notifications\PasswordResetCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_reset_flow(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'reset@example.com']);

        $this->postJson('/api/password/forgot', ['email' => 'reset@example.com'])
            ->assertOk();

        Notification::assertSentTo($user, PasswordResetCodeNotification::class);

        $record = PasswordResetToken::where('email', 'reset@example.com')->firstOrFail();
        $code = $this->extractCodeFromNotification($user);

        $this->postJson('/api/password/verify-code', [
            'email' => 'reset@example.com',
            'code' => $code,
        ])->assertOk();

        $this->postJson('/api/password/reset', [
            'email' => 'reset@example.com',
            'code' => $code,
            'password' => 'nova-senha-6',
            'password_confirmation' => 'nova-senha-6',
        ])->assertOk();

        $this->assertTrue(Hash::check('nova-senha-6', $user->fresh()->password));
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_forgot_password_does_not_reveal_unknown_email(): void
    {
        Notification::fake();

        $this->postJson('/api/password/forgot', ['email' => 'ninguem@example.com'])
            ->assertOk();

        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_wrong_code_is_rejected(): void
    {
        Notification::fake();

        User::factory()->create(['email' => 'reset2@example.com']);

        $this->postJson('/api/password/forgot', ['email' => 'reset2@example.com'])->assertOk();

        $this->postJson('/api/password/verify-code', [
            'email' => 'reset2@example.com',
            'code' => '000000',
        ])->assertUnprocessable()->assertJsonValidationErrors('code');
    }

    public function test_expired_code_is_rejected(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'reset3@example.com']);

        $this->postJson('/api/password/forgot', ['email' => 'reset3@example.com'])->assertOk();

        $code = $this->extractCodeFromNotification($user);

        PasswordResetToken::where('email', 'reset3@example.com')->update([
            'expires_at' => now()->subMinute(),
        ]);

        $this->postJson('/api/password/verify-code', [
            'email' => 'reset3@example.com',
            'code' => $code,
        ])->assertUnprocessable()->assertJsonValidationErrors('code');
    }

    public function test_reset_revokes_all_existing_sessions(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'reset4@example.com',
            'password' => Hash::make('senha-antiga'),
        ]);

        $login = $this->postJson('/api/login', [
            'email' => 'reset4@example.com',
            'password' => 'senha-antiga',
        ])->json();

        $this->postJson('/api/password/forgot', ['email' => 'reset4@example.com'])->assertOk();
        $code = $this->extractCodeFromNotification($user);

        $this->postJson('/api/password/reset', [
            'email' => 'reset4@example.com',
            'code' => $code,
            'password' => 'senha-nova-123',
            'password_confirmation' => 'senha-nova-123',
        ])->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$login['access_token'])
            ->getJson('/api/user')
            ->assertUnauthorized();
    }

    /**
     * A notificação é fake (não envia de verdade); recupera o código gerado
     * lendo o argumento passado ao construtor da notification enfileirada.
     */
    private function extractCodeFromNotification(User $user): string
    {
        $code = null;

        Notification::assertSentTo(
            $user,
            PasswordResetCodeNotification::class,
            function (PasswordResetCodeNotification $notification) use (&$code) {
                $code = (fn () => $this->code)->call($notification);

                return true;
            },
        );

        return $code;
    }
}
