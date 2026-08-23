<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Colunas de sinalização de dado mock/teste nas 17 tabelas de entidade
     * core. Migration nova (não edita as já commitadas) — ver `HasMockFlag`
     * pros scopes `real()`/`mock()` e `mock:wipe` pra limpeza.
     *
     * @var list<string>
     */
    private const TABLES = [
        'products',
        'vendedores',
        'flock',
        'flock_incubations',
        'hatch_events',
        'vendor_stock',
        'sales',
        'sale_exclusions',
        'stock_transfers',
        'daily_productions',
        'egg_stocks',
        'expenses',
        'expense_species_overrides',
        'cash_flows',
        'feed_stocks',
        'feed_open_logs',
        'flock_cleanings',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->boolean('is_mock')->default(false)->index();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('is_mock');
            });
        }
    }
};
