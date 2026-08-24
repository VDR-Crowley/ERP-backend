<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trava contra reimportação duplicada de planilha: não faz sentido duas
 * higienizações do mesmo tipo, mesma espécie, no mesmo dia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flock_cleanings', function (Blueprint $table) {
            $table->unique(['date', 'species', 'cleaning_type'], 'flock_cleanings_duplicate_unique');
        });
    }

    public function down(): void
    {
        Schema::table('flock_cleanings', function (Blueprint $table) {
            $table->dropUnique('flock_cleanings_duplicate_unique');
        });
    }
};
