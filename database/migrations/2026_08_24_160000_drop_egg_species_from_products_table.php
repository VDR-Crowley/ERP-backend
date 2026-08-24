<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reverso de `add_egg_species_to_products_table`: usuário decidiu voltar pro
 * estoque simples e manual (coluna `stock` crua sempre), sem cálculo
 * automático do Plantel a partir de produção/vendas/perda. Migration nova de
 * drop (não edita a antiga, que já rodou em produção) — ver
 * `drop_egg_losses_table` na sequência, mesma decisão.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('egg_species');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->enum('egg_species', ['quail', 'chicken'])->nullable()->after('eggs_per_unit');
        });
    }
};
