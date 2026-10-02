<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perfil VENDEDOR: liga um usuário (login) a um `vendedor`. O login da Karol
 * aponta pro vendedor "Karol", e o backend usa isso pra escopar o que ela vê/
 * cria (só as vendas dela, só os clientes dela). Null = usuário comum (admin).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('vendedor_id')
                ->nullable()
                ->after('role')
                ->constrained('vendedores')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vendedor_id');
        });
    }
};
