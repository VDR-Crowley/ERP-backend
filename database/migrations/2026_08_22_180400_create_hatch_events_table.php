<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hatch_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flock_incubation_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->integer('count');
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hatch_events');
    }
};
