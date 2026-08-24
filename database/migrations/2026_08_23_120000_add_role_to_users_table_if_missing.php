<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rede de segurança: `role` foi adicionada editando a migration
     * `0001_01_01_000000_create_users_table` já commitada (commit aa2f717),
     * em vez de uma migration nova. Laravel só rastreia migration por NOME
     * na tabela `migrations` — bancos onde `create_users_table` já tinha
     * rodado antes daquele commit (produção/Railway) nunca reexecutam o
     * arquivo alterado, então ficam com a migration marcada como aplicada
     * mas sem a coluna de verdade. `migrate --force` reporta "Nothing to
     * migrate" nesse cenário. Idem pra `is_mock`/`is_active`: conferido
     * que ambas vieram de migrations novas (não edição), sem esse problema.
     *
     * `hasColumn` guard: idempotente pra não quebrar bancos (locais, CI)
     * onde a coluna já existe de verdade.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role')->default('ADMINISTRADOR')->after('email');
            }
        });
    }

    public function down(): void
    {
        // Sem down: coluna pertence à migration original de `users`;
        // não é dessa migration a responsabilidade de removê-la.
    }
};
