<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Headcount atual do plantel por linha (`species` é rótulo livre digitado
     * pelo usuário, ex. "Galinhas Embrapa 051" — não é o enum
     * quail/chicken/codorna/galinha usado em outras tabelas; ver decisão
     * pendente em docs/plano-entidades.md).
     */
    public function up(): void
    {
        Schema::create('flock', function (Blueprint $table) {
            $table->id();
            $table->string('species');
            $table->integer('quantity');
            $table->integer('feed_bags_per_month');
            $table->decimal('bag_price', 10, 2);
            $table->decimal('monthly_total', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flock');
    }
};
