<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Sale;
use App\Services\StockLocationService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Repara o bug real de produção (2026-08-23): reimportação da planilha de
 * negócio triplicou ~116 vendas reais, cada cópia debitando estoque de novo
 * na criação (`StockLocationService`, chamado por `SaleService::create()`).
 *
 * Mesma chave de negócio da trava em `StoreSaleRequest`/migration
 * `sales_duplicate_unique`: date + product_id + quantity + unit_price +
 * buyer + seller_id. Dentro de cada grupo duplicado, mantém o registro de
 * menor id (mais antigo) e remove o resto, devolvendo ao local de estoque de
 * cada duplicata removida a quantidade que ela debitou na criação.
 *
 * Roda em modo dry-run por padrão (só mostra o que seria feito); só altera
 * dado/estoque com `--force`. Idempotente: depois de um `--force`
 * bem-sucedido, rodar de novo não acha mais grupos duplicados.
 */
class DeduplicateSales extends Command
{
    protected $signature = 'sales:deduplicate {--force : Executa de verdade (remove duplicatas e devolve estoque); sem essa flag só mostra o que seria feito}';

    protected $description = 'Remove vendas duplicadas (reimportação de planilha) e devolve o estoque debitado a mais por elas';

    public function handle(StockLocationService $stock): int
    {
        $force = (bool) $this->option('force');

        $duplicateGroups = Sale::query()
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Sale $sale) => implode('|', [
                $sale->date->toDateString(),
                $sale->product_id,
                $sale->quantity,
                $sale->unit_price,
                $sale->buyer,
                $sale->seller_id,
            ]))
            ->filter(fn (Collection $group) => $group->count() > 1);

        if ($duplicateGroups->isEmpty()) {
            $this->info('Nenhuma venda duplicada encontrada.');

            return self::SUCCESS;
        }

        $products = Product::query()->get()->keyBy('id');
        $removedIds = [];
        $rows = [];
        /** @var array<int, int> product_id => quantidade a restaurar/restaurada */
        $stockByProduct = [];

        foreach ($duplicateGroups as $group) {
            $kept = $group->sortBy('id')->first();

            foreach ($group->reject(fn (Sale $sale) => $sale->id === $kept->id) as $duplicate) {
                $product = $products->get($duplicate->product_id);

                $removedIds[] = $duplicate->id;
                $stockByProduct[$duplicate->product_id] = ($stockByProduct[$duplicate->product_id] ?? 0) + $duplicate->quantity;

                $rows[] = [
                    $duplicate->id,
                    $kept->id,
                    $product?->name ?? "produto #{$duplicate->product_id}",
                    $duplicate->date->toDateString(),
                    $duplicate->quantity,
                    $duplicate->buyer,
                ];

                if ($force) {
                    DB::transaction(function () use ($duplicate, $product, $stock): void {
                        // Delta positivo: desfaz a baixa que essa duplicata causou na criação (SaleService::create()).
                        $stock->adjust(
                            $duplicate->stock_location_type,
                            $duplicate->stock_location_vendedor_id,
                            $product,
                            $duplicate->quantity,
                        );

                        $duplicate->delete();
                    });
                }
            }
        }

        $this->table(['ID removido', 'ID mantido', 'Produto', 'Data', 'Qtd.', 'Comprador'], $rows);

        $this->newLine();
        $this->table(
            ['Produto', $force ? 'Estoque restaurado' : 'Estoque a restaurar'],
            collect($stockByProduct)
                ->map(fn (int $qty, int $productId) => [$products->get($productId)?->name ?? "produto #{$productId}", $qty])
                ->values()
                ->all()
        );

        $totalGroups = $duplicateGroups->count();
        $totalRemoved = count($removedIds);

        $summary = $force
            ? "Removida(s) {$totalRemoved} venda(s) duplicada(s) em {$totalGroups} grupo(s); estoque restaurado."
            : "[DRY-RUN] {$totalRemoved} venda(s) duplicada(s) em {$totalGroups} grupo(s) seriam removidas; nada foi alterado. Rode com --force para executar de verdade.";

        $this->info($summary);

        Log::info('sales:deduplicate executado', [
            'force' => $force,
            'groups' => $totalGroups,
            'removed_ids' => $removedIds,
            'stock_restored_by_product' => $stockByProduct,
        ]);

        return self::SUCCESS;
    }
}
