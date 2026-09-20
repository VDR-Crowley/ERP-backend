<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * O `stock_location_type` de `sales`/`stock_transfers` nasceu como enum
 * (['plantel','vendedor']). No Postgres, enum vira varchar + uma CHECK
 * constraint `{tabela}_{coluna}_check`. As migrações que trocaram o enum por
 * string (pra aceitar 'barn') NÃO removeram essa CHECK no Postgres — então
 * produção rejeitava 'barn' com SQLSTATE[23514]. No SQLite (local) a check
 * nem existe, por isso só quebrava em produção. Aqui removemos as checks
 * antigas. Idempotente (IF EXISTS) e só roda no Postgres.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE sales DROP CONSTRAINT IF EXISTS sales_stock_location_type_check');
        DB::statement('ALTER TABLE stock_transfers DROP CONSTRAINT IF EXISTS stock_transfers_from_location_type_check');
        DB::statement('ALTER TABLE stock_transfers DROP CONSTRAINT IF EXISTS stock_transfers_to_location_type_check');
    }

    public function down(): void
    {
        // Sem reversão: recriar a check antiga voltaria a barrar 'barn'.
    }
};
