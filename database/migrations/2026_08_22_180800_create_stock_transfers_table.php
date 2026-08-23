<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->integer('quantity');
            $table->enum('from_location_type', ['plantel', 'vendedor']);
            $table->foreignId('from_vendedor_id')->nullable()->constrained('vendedores')->nullOnDelete();
            $table->enum('to_location_type', ['plantel', 'vendedor']);
            $table->foreignId('to_vendedor_id')->nullable()->constrained('vendedores')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfers');
    }
};
