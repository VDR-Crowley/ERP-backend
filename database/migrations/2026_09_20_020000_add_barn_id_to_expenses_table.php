<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Despesa por galpão: `barn_id` opcional (nullable) em `expenses`. Nullable
 * porque nem toda despesa é atribuível a um galpão específico (custos gerais).
 * No relatório por galpão, ao filtrar um galpão contam só as despesas com
 * aquele `barn_id` — as gerais (null) ficam de fora do payback de um galpão.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('barn_id')->nullable()->after('category')
                ->constrained('barn')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('barn_id');
        });
    }
};
