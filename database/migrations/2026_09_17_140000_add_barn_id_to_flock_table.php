<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Liga cada lote de aves (flock = espécie+quantidade) a um galpão (barn).
 * Aditivo e opcional (nullable): NÃO altera species/quantity nem toca em
 * daily_productions, então produção e relatórios continuam funcionando.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flock', function (Blueprint $table) {
            $table->foreignId('barn_id')
                ->nullable()
                ->after('id')
                ->constrained('barn')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('flock', function (Blueprint $table) {
            $table->dropConstrainedForeignId('barn_id');
        });
    }
};
