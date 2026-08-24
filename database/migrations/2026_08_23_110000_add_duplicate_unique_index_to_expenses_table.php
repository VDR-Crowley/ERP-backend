<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trava contra reimportação duplicada de planilha: mesma despesa
 * (date + description + category + amount) não pode existir duas vezes.
 * `category` entra na chave pra não bloquear a mesma despesa (mesmo dia,
 * mesma descrição, mesmo valor) legitimamente lançada em categorias
 * diferentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->unique(['date', 'description', 'category', 'amount'], 'expenses_duplicate_unique');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropUnique('expenses_duplicate_unique');
        });
    }
};
