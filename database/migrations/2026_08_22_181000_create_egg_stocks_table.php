<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
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
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('egg_stocks');
    }
};
