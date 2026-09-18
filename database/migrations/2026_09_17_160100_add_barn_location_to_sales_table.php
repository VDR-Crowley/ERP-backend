<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vendas ganham o local de estoque "galpão": nova coluna
 * `stock_location_barn_id` e o `stock_location_type` deixa de ser enum
 * fixo ['plantel','vendedor'] pra aceitar também 'barn'. Validação dos
 * valores fica no FormRequest (mais portável que enum no SQLite).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('stock_location_barn_id')
                ->nullable()
                ->after('stock_location_vendedor_id')
                ->constrained('barn')
                ->nullOnDelete();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->string('stock_location_type')->default('plantel')->change();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stock_location_barn_id');
        });
    }
};
