<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo CRM: um registro por comprador ÚNICO (pelo nome). O telefone mora só
 * aqui (não existe em `sales`). Novos compradores entram sozinhos ao cadastrar
 * uma venda (ver SaleService::create -> Customer::firstOrCreate), e os já
 * existentes são migrados pela migration de backfill que roda em seguida.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('phone')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
