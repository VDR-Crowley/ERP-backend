<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trava contra reimportação duplicada / double-click: mesma perda (date +
 * species + quantity + reason idêntico) não pode existir duas vezes. Ao
 * contrário de Higienização (que exclui `notes` da chave por ser comentário
 * livre), aqui `reason` entra na chave por pedido explícito do usuário — é o
 * campo que normalmente distingue duas perdas legítimas no mesmo dia/espécie
 * (ex.: quebrou de manhã E teve doação à tarde). `reason` nulo em duas linhas
 * não colide no índice do banco (NULL não é igual a NULL), então quem
 * garante o bloqueio nesse caso é a validação em `StoreEggLossRequest`; o
 * índice aqui é a rede de segurança pra escrita fora da API (import direto).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('egg_losses', function (Blueprint $table) {
            $table->unique(['date', 'species', 'quantity', 'reason'], 'egg_losses_duplicate_unique');
        });
    }

    public function down(): void
    {
        Schema::table('egg_losses', function (Blueprint $table) {
            $table->dropUnique('egg_losses_duplicate_unique');
        });
    }
};
