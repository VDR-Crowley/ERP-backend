<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `egg_species` relaciona explicitamente um produto à coluna de
     * `daily_productions` que o abastece (`quail_eggs`/`chicken_eggs`).
     * Substitui a heurística por nome de produto que existia no front antigo
     * removido. Null = produto fora dessa lógica (estoque simples de sempre,
     * ver `Product::stock()`); só os 2 produtos de ovo unitário entram
     * (decisão de escopo: produtos que dividiriam a mesma produção com outro
     * ou misturam espécies ficam de fora até existir regra de rateio).
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->enum('egg_species', ['quail', 'chicken'])->nullable()->after('eggs_per_unit');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('egg_species');
        });
    }
};
