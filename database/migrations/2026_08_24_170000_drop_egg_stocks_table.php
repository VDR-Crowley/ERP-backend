<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reverso de `create_egg_stocks_table`: entidade Estoque de Ovos removida —
 * redundante com o campo `stock` manual de Produtos (mesma decisão de
 * `drop_egg_losses_table`). Migration nova de drop (não edita a antiga, que
 * já rodou em produção).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('egg_stocks');
    }

    public function down(): void
    {
        Schema::create('egg_stocks', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->integer('quail_eggs')->nullable();
            $table->integer('chicken_eggs')->nullable();
            $table->decimal('quail_packs', 10, 2)->default(0);
            $table->decimal('chicken_packs', 10, 2)->default(0);
            $table->decimal('quail_stock_value', 10, 2)->default(0);
            $table->decimal('chicken_stock_value', 10, 2)->default(0);
            $table->boolean('is_mock')->default(false)->index();
            $table->timestamps();
        });
    }
};
