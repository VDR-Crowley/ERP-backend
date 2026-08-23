<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_species_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->unique()->constrained()->cascadeOnDelete();
            $table->enum('species', ['quail', 'chicken'])->nullable();
            $table->text('reason');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_species_overrides');
    }
};
