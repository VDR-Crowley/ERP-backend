<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `feed_stock_id` nullable + `feed_type` redundante de propósito: o
     * front resolve o tipo aberto por texto, não por id — a FK é nula
     * quando o texto não bate com nenhum `feed_stocks.type` cadastrado (log
     * não trava por isso). Ver decisão pendente em docs/plano-entidades.md.
     */
    public function up(): void
    {
        Schema::create('feed_open_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feed_stock_id')->nullable()->constrained('feed_stocks')->nullOnDelete();
            $table->string('feed_type');
            $table->date('date');
            $table->decimal('weight_kg', 8, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_open_logs');
    }
};
