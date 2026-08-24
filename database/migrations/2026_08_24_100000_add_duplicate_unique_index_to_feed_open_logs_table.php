<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trava contra duplicata de saco aberto (double-click/retry em
 * POST /feed-stocks/{feed_stock}/open-bag): mesmo date + feed_type +
 * weight_kg não pode existir duas vezes. Bug real de produção
 * (2026-08-24): reenvio sem trava duplicou o "Histórico de sacos abertos",
 * cada cópia debitando bags_in_stock/kg_in_stock de novo.
 *
 * Chave por feed_type (texto cru), não feed_stock_id: mesmo motivo
 * documentado na migration de feed_open_logs — o texto não precisa bater com
 * feed_stocks.type, então feed_stock_id pode ser nulo (múltiplos nulos não
 * colidem em índice único).
 *
 * DEPLOY SÓ DEPOIS de `feed-open-logs:deduplicate --force` já ter rodado
 * com sucesso em produção (ver commit 7363f04). `feed_open_logs` em
 * produção ainda tem os grupos duplicados reais desse bug até esse comando
 * limpar — se essa migration subir antes, `migrate --force` falha criando o
 * índice sobre dado duplicado e o boot inteiro do App Service cai
 * (`Dockerfile`: `migrate --force && artisan serve`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feed_open_logs', function (Blueprint $table) {
            $table->unique(['date', 'feed_type', 'weight_kg'], 'feed_open_logs_duplicate_unique');
        });
    }

    public function down(): void
    {
        Schema::table('feed_open_logs', function (Blueprint $table) {
            $table->dropUnique('feed_open_logs_duplicate_unique');
        });
    }
};
