<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flock_cleanings', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->enum('species', ['quail', 'chicken']);
            $table->enum('cleaning_type', ['total', 'feeder', 'tray', 'nest']);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flock_cleanings');
    }
};
