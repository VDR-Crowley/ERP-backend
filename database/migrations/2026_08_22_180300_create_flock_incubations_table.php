<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lote de incubação ("Novo Plantel" no front). O total nascido não vem
     * de coluna própria — é a soma de `hatch_events` (eclosão não é
     * instantânea, leva vários dias). Campos legados do front
     * (`actualHatchDate`/`hatchedCount`, pré-histórico incremental) não
     * entram aqui: no backend `hatch_events` já nasce como fonte única.
     */
    public function up(): void
    {
        Schema::create('flock_incubations', function (Blueprint $table) {
            $table->id();
            $table->date('start_date');
            $table->enum('species', ['quail', 'chicken']);
            $table->integer('egg_count');
            $table->date('expected_hatch_date');
            $table->enum('status', ['incubando', 'eclodido'])->default('incubando');
            $table->decimal('egg_cost', 10, 2)->nullable();
            $table->decimal('feed_cost', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flock_incubations');
    }
};
