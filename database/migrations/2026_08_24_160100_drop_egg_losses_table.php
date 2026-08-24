<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reverso de `create_egg_losses_table` +
 * `add_duplicate_unique_index_to_egg_losses_table`: entidade Perda de Ovos
 * removida — voltou pro estoque simples e manual (ver
 * `drop_egg_species_from_products_table`). Migration nova de drop (não edita
 * as antigas, que já rodaram em produção).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('egg_losses');
    }

    public function down(): void
    {
        Schema::create('egg_losses', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->enum('species', ['quail', 'chicken']);
            $table->unsignedInteger('quantity');
            $table->string('reason')->nullable();
            $table->boolean('is_mock')->default(false)->index();
            $table->timestamps();

            $table->unique(['date', 'species', 'quantity', 'reason'], 'egg_losses_duplicate_unique');
        });
    }
};
