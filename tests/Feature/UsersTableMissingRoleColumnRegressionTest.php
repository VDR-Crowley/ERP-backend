<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Reproduz o cenário real de produção: `role` foi adicionada editando a
 * migration `0001_01_01_000000_create_users_table` já commitada (commit
 * aa2f717), em vez de uma migration nova. Laravel só rastreia migration por
 * NOME na tabela `migrations` — um banco onde essa migration já tinha
 * rodado antes daquele commit fica com ela marcada como aplicada, mas sem a
 * coluna `role` de verdade (`migrate --force` reporta "Nothing to migrate").
 *
 * Não dá pra reproduzir isso via `Artisan::call('migrate')` normal — a
 * `migrations` table de teste não tem esse desalinhamento histórico. Em vez
 * disso, simula o sintoma diretamente: dropa `role` (como se fosse o banco
 * de produção antigo) e chama a migration nova isolada, do jeito que
 * `migrate --force` chamaria se ela estivesse pendente.
 *
 * Ver [[railway-migrate-safety-net]] pro incidente irmão (tabelas inteiras
 * faltando porque `migrate --force` não rodava no boot).
 */
class UsersTableMissingRoleColumnRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_role_migration_restores_missing_column_on_a_stale_production_like_database(): void
    {
        $this->assertTrue(
            Schema::hasColumn('users', 'role'),
            'baseline: migrate:fresh local já deixa `role` presente.',
        );

        // Simula o banco de produção antigo: `create_users_table` já
        // "rodou" (fica marcada em `migrations`), mas sem a coluna `role`
        // de verdade porque a edição na migration veio depois.
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        $this->assertFalse(
            Schema::hasColumn('users', 'role'),
            'sintoma reproduzido: coluna ausente mesmo com a migration "aplicada".',
        );

        $migration = require database_path('migrations/2026_08_23_120000_add_role_to_users_table_if_missing.php');
        $migration->up();

        $this->assertTrue(
            Schema::hasColumn('users', 'role'),
            'migration nova deve recriar a coluna que faltava.',
        );
    }

    public function test_add_role_migration_is_idempotent_when_column_already_exists(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'role'));

        $migration = require database_path('migrations/2026_08_23_120000_add_role_to_users_table_if_missing.php');

        // Não deve lançar "duplicate column" nem nada parecido.
        $migration->up();

        $this->assertTrue(Schema::hasColumn('users', 'role'));
    }
}
