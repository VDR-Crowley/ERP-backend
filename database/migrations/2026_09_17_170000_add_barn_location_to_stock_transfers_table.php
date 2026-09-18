<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transferências ganham galpão como origem/destino: colunas
 * `from_location_barn_id`/`to_location_barn_id` e os `*_location_type` deixam
 * de ser enum fixo pra aceitar 'barn'. Validação fica no FormRequest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->foreignId('from_location_barn_id')->nullable()->after('from_vendedor_id')->constrained('barn')->nullOnDelete();
            $table->foreignId('to_location_barn_id')->nullable()->after('to_vendedor_id')->constrained('barn')->nullOnDelete();
        });

        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->string('from_location_type')->change();
            $table->string('to_location_type')->change();
        });
    }

    public function down(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('from_location_barn_id');
            $table->dropConstrainedForeignId('to_location_barn_id');
        });
    }
};
