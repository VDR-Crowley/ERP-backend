<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Produção por galpão: cada lançamento passa a ser (data + galpão). A
 * unicidade deixa de ser só `date` e vira (`date`, `barn_id`) — permite 1
 * registro por dia POR galpão. barn_id é nullable pra não invalidar os dados
 * legados (produção global antiga, sem galpão).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_productions', function (Blueprint $table) {
            $table->foreignId('barn_id')
                ->nullable()
                ->after('id')
                ->constrained('barn')
                ->nullOnDelete();
        });

        Schema::table('daily_productions', function (Blueprint $table) {
            $table->dropUnique(['date']);
            $table->unique(['date', 'barn_id']);
        });
    }

    public function down(): void
    {
        Schema::table('daily_productions', function (Blueprint $table) {
            $table->dropUnique(['date', 'barn_id']);
            $table->unique('date');
        });

        Schema::table('daily_productions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('barn_id');
        });
    }
};
