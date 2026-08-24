<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trava contra reimportação duplicada de planilha: mesma venda
 * (date + product_id + quantity + unit_price + buyer + seller_id) não pode
 * existir duas vezes. Bug real de produção (2026-08-23): reimportação
 * triplicou ~116 vendas reais, cada uma debitando estoque de novo via
 * `StockLocationService` na criação.
 *
 * `unit_price` entra na chave porque já é a chave natural usada em
 * `ImportPlanilha::importVendas()` (reimportação idempotente); sem ela, duas
 * vendas do mesmo produto/quantidade pro mesmo comprador no mesmo dia com
 * preços diferentes (negociação pontual) seriam bloqueadas incorretamente.
 * `stock_location_type`/`stock_location_vendedor_id` ficam de fora: uma
 * reimportação duplicada carrega o mesmo local de estoque também, então não
 * precisam entrar na chave pra pegar o caso real.
 *
 * DEPLOY SÓ DEPOIS de `sales:deduplicate --force` já ter rodado com sucesso
 * em produção (ver commit f6ee563). `sales` em produção ainda tem os grupos
 * duplicados reais desse bug até esse comando limpar — se essa migration
 * subir antes, `migrate --force` falha criando o índice sobre dado
 * duplicado e o boot inteiro do App Service cai (`Dockerfile`:
 * `migrate --force && artisan serve`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->unique(['date', 'product_id', 'quantity', 'unit_price', 'buyer', 'seller_id'], 'sales_duplicate_unique');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropUnique('sales_duplicate_unique');
        });
    }
};
