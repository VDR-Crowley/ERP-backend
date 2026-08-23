<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Tela de administração de usuários — diferente do cadastro público (`/register`). */
class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        Sanctum::actingAs($this->admin, ['access']);
    }

    private User $admin;

    public function test_lists_all_users_without_pagination(): void
    {
        User::factory()->count(3)->create();

        $response = $this->getJson('/api/users')->assertOk();

        $this->assertCount(4, $response->json()); // 3 + o admin logado
    }

    public function test_creates_user_with_administrador_role(): void
    {
        $response = $this->postJson('/api/users', [
            'name' => 'Novo Usuário',
            'email' => 'novo@example.com',
            'password' => 'segredo6',
            'password_confirmation' => 'segredo6',
        ]);

        $response->assertCreated()
            ->assertJsonPath('role', User::ROLE_ADMINISTRADOR)
            ->assertJsonPath('is_active', true);

        $this->assertDatabaseHas('users', [
            'email' => 'novo@example.com',
            'role' => User::ROLE_ADMINISTRADOR,
            'is_active' => true,
        ]);
    }

    public function test_create_response_never_leaks_password(): void
    {
        $response = $this->postJson('/api/users', [
            'name' => 'Novo Usuário',
            'email' => 'semsenha@example.com',
            'password' => 'segredo6',
            'password_confirmation' => 'segredo6',
        ]);

        $response->assertJsonMissingPath('password')
            ->assertJsonMissingPath('password_hash');
        $this->assertArrayNotHasKey('password', $response->json());
    }

    public function test_create_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'ja-existe@example.com']);

        $this->postJson('/api/users', [
            'name' => 'Outro',
            'email' => 'ja-existe@example.com',
            'password' => 'segredo6',
            'password_confirmation' => 'segredo6',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_updates_name_and_email(): void
    {
        $user = User::factory()->create();

        $this->putJson("/api/users/{$user->id}", [
            'name' => 'Nome Editado',
            'email' => $user->email,
        ])->assertOk()->assertJsonPath('name', 'Nome Editado');

        $this->assertSame('Nome Editado', $user->fresh()->name);
    }

    public function test_updates_password_only_when_sent(): void
    {
        $user = User::factory()->create();
        $originalHash = $user->password;

        $this->putJson("/api/users/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
        ])->assertOk();

        $this->assertSame($originalHash, $user->fresh()->password);

        $this->putJson("/api/users/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'nova-senha6',
            'password_confirmation' => 'nova-senha6',
        ])->assertOk();

        $this->assertNotSame($originalHash, $user->fresh()->password);
    }

    public function test_deactivates_via_update(): void
    {
        $user = User::factory()->create();

        $this->putJson("/api/users/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => false,
        ])->assertOk()->assertJsonPath('is_active', false);

        $this->assertFalse($user->fresh()->is_active);
    }

    public function test_destroy_deactivates_instead_of_deleting(): void
    {
        $user = User::factory()->create();

        $this->deleteJson("/api/users/{$user->id}")->assertNoContent();

        $this->assertModelExists($user);
        $this->assertFalse($user->fresh()->is_active);
    }

    public function test_cannot_deactivate_own_account_via_update(): void
    {
        $this->putJson("/api/users/{$this->admin->id}", [
            'name' => $this->admin->name,
            'email' => $this->admin->email,
            'is_active' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors('is_active');

        $this->assertTrue($this->admin->fresh()->is_active);
    }

    public function test_cannot_destroy_own_account(): void
    {
        $this->deleteJson("/api/users/{$this->admin->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('is_active');

        $this->assertTrue($this->admin->fresh()->is_active);
    }

    public function test_deactivated_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('senha-correta'),
        ]);
        $user->update(['is_active' => false]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'senha-correta',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }
}
