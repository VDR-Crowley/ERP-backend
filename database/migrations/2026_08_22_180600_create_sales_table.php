<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `stock_location_type`/`stock_location_vendedor_id` substituem a string
     * única do front (`'plantel'` ou `vendedor:<id>`) por duas colunas
     * relacionais. `buyer` é texto livre (comprador não é necessariamente um
     * `vendedor` cadastrado); `seller_id` é o vendedor que fez a venda.
     */
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total', 10, 2);
            $table->boolean('payment_pending');
            $table->string('buyer');
            $table->foreignId('seller_id')->constrained('vendedores')->restrictOnDelete();
            $table->boolean('delivery_pending');
            $table->date('delivery_date')->nullable();
            $table->enum('stock_location_type', ['plantel', 'vendedor'])->default('plantel');
            $table->foreignId('stock_location_vendedor_id')->nullable()->constrained('vendedores')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
