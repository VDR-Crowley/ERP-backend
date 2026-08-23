<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Apaga só os registros `is_mock = true` das 17 tabelas de entidade core.
 * Ordem de deleção respeita as FKs `restrictOnDelete` (sales -> products/
 * vendedores, stock_transfers -> products): filhos primeiro, pais depois.
 */
class WipeMockData extends Command
{
    protected $signature = 'mock:wipe {--dry-run : Só mostra quantos registros seriam apagados, sem apagar}';

    protected $description = 'Apaga todos os registros marcados como is_mock = true nas tabelas de entidade core';

    /**
     * Filhos antes dos pais que eles referenciam via FK.
     *
     * @var list<string>
     */
    private const TABLES_IN_DELETE_ORDER = [
        'hatch_events',
        'sale_exclusions',
        'expense_species_overrides',
        'feed_open_logs',
        'vendor_stock',
        'stock_transfers',
        'sales',
        'flock_incubations',
        'expenses',
        'products',
        'vendedores',
        'flock',
        'daily_productions',
        'egg_stocks',
        'cash_flows',
        'feed_stocks',
        'flock_cleanings',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $total = 0;

        foreach (self::TABLES_IN_DELETE_ORDER as $table) {
            $count = DB::table($table)->where('is_mock', true)->count();
            $total += $count;

            if ($dryRun) {
                $this->line("{$table}: {$count} registro(s) mock (dry-run, nada apagado)");

                continue;
            }

            DB::table($table)->where('is_mock', true)->delete();
            $this->line("{$table}: {$count} registro(s) mock apagado(s)");
        }

        $this->info($dryRun
            ? "Total: {$total} registro(s) mock encontrados (dry-run)."
            : "Total: {$total} registro(s) mock apagados.");

        return self::SUCCESS;
    }
}
