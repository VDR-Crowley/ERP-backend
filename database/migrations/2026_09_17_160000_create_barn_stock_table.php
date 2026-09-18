<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estoque de produto por GALPÃO (barn). Mesmo padrão de `vendor_stock`: um
 * registro por par (galpão, produto). É o "local Plantel" antes global
 * (products.stock) agora separado por galpão — ver StockLocationService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barn_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barn_id')->constrained('barn')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(0);
            $table->boolean('is_mock')->default(false);
            $table->timestamps();

            $table->unique(['barn_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barn_stock');
    }
};
