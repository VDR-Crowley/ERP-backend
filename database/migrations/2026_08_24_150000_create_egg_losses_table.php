<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perda de ovos crus: quebra, consumo próprio, doação — qualquer ovo que saiu
 * da produção sem virar venda. Descontado em `Product::calculatePlantelEggStock()`
 * pra fechar a diferença entre o estoque calculado (produção - vendas ±
 * transferência) e a contagem física real, que hoje não tinha pra onde ir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('egg_losses', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->enum('species', ['quail', 'chicken']);
            $table->unsignedInteger('quantity');
            $table->string('reason')->nullable();
            $table->boolean('is_mock')->default(false)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('egg_losses');
    }
};
