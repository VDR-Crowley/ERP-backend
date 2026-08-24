<?php

namespace App\Console\Commands;

use App\Models\Sale;
use App\Models\StockTransfer;
use App\Models\Vendedor;
use App\Models\VendorStock;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Repara o bug real de produção (2026-08-24): reimportação da aba
 * "Vendedores" recriou os 6 vendedores do zero em vez de dar upsert
 * (`vendedores.name` não tem índice único, diferente de `products.name`),
 * gerando 12 linhas pra 6 vendedores reais. Toda venda que referenciava a
 * cópia "nova" ficou com `seller_id` diferente da cópia "antiga" do mesmo
 * vendedor de negócio — o que escondeu 117 vendas duplicadas de
 * `sales:deduplicate` (agrupa por `seller_id` cru, que genuinamente
 * divergia mesmo com todo campo visível idêntico).
 *
 * Agrupa `vendedores` por nome normalizado (trim + minúsculo), mantém a
 * cópia de menor id (mais antiga) como canônica e repointa toda FK que
 * referencia `vendedores.id`: `sales.seller_id`,
 * `sales.stock_location_vendedor_id`, `vendor_stock.vendedor_id`,
 * `stock_transfers.from_vendedor_id`/`to_vendedor_id`. `vendor_stock` tem
 * unique(product_id, vendedor_id): quando a cópia canônica já tem saldo pro
 * mesmo produto, soma as quantidades em vez de repointar direto (repointar
 * direto violaria a constraint).
 *
 * Roda em modo dry-run por padrão; só altera dado com `--force`.
 * Idempotente: depois de um `--force` bem-sucedido, rodar de novo não acha
 * mais grupos duplicados.
 *
 * Rodar ANTES de `sales:deduplicate` (ver App\Console\Commands\DeduplicateSales)
 * — só com `seller_id` consolidado o dedupe de vendas consegue ver as
 * duplicatas de negócio escondidas atrás do vendedor duplicado.
 */
class MergeDuplicateVendedores extends Command
{
    protected $signature = 'vendedores:merge-duplicates {--force : Executa de verdade (repointa FKs e remove vendedores redundantes); sem essa flag só mostra o que seria feito}';

    protected $description = 'Funde vendedores duplicados (mesmo nome normalizado, ids diferentes) numa única linha canônica e repointa as FKs que os referenciam';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        $duplicateGroups = Vendedor::query()
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Vendedor $vendedor) => mb_strtolower(trim($vendedor->name)))
            ->filter(fn (Collection $group) => $group->count() > 1);

        if ($duplicateGroups->isEmpty()) {
            $this->info('Nenhum vendedor duplicado encontrado.');

            return self::SUCCESS;
        }

        $rows = [];
        $removedIds = [];

        foreach ($duplicateGroups as $group) {
            $canonical = $group->sortBy('id')->first();

            foreach ($group->reject(fn (Vendedor $vendedor) => $vendedor->id === $canonical->id) as $duplicate) {
                $rows[] = [$duplicate->id, $canonical->id, $duplicate->name];
                $removedIds[] = $duplicate->id;

                if ($force) {
                    DB::transaction(function () use ($duplicate, $canonical): void {
                        $this->repoint($duplicate, $canonical);
                    });
                }
            }
        }

        $this->table(['ID removido', 'ID canônico', 'Nome'], $rows);

        $totalGroups = $duplicateGroups->count();
        $totalRemoved = count($removedIds);

        $summary = $force
            ? "Fundido(s) {$totalRemoved} vendedor(es) duplicado(s) em {$totalGroups} grupo(s)."
            : "[DRY-RUN] {$totalRemoved} vendedor(es) duplicado(s) em {$totalGroups} grupo(s) seriam fundidos; nada foi alterado. Rode com --force para executar de verdade.";

        $this->info($summary);

        Log::info('vendedores:merge-duplicates executado', [
            'force' => $force,
            'groups' => $totalGroups,
            'removed_ids' => $removedIds,
        ]);

        return self::SUCCESS;
    }

    private function repoint(Vendedor $duplicate, Vendedor $canonical): void
    {
        Sale::query()->where('seller_id', $duplicate->id)->update(['seller_id' => $canonical->id]);
        Sale::query()->where('stock_location_vendedor_id', $duplicate->id)->update(['stock_location_vendedor_id' => $canonical->id]);

        StockTransfer::query()->where('from_vendedor_id', $duplicate->id)->update(['from_vendedor_id' => $canonical->id]);
        StockTransfer::query()->where('to_vendedor_id', $duplicate->id)->update(['to_vendedor_id' => $canonical->id]);

        foreach (VendorStock::query()->where('vendedor_id', $duplicate->id)->get() as $stock) {
            $canonicalStock = VendorStock::query()
                ->where('product_id', $stock->product_id)
                ->where('vendedor_id', $canonical->id)
                ->first();

            if ($canonicalStock !== null) {
                // Colisão: os dois vendedores tinham saldo pro mesmo
                // produto. Soma no canônico e descarta a linha da
                // duplicata — repointar direto violaria unique(product_id, vendedor_id).
                $canonicalStock->increment('quantity', $stock->quantity);
                $stock->delete();
            } else {
                $stock->update(['vendedor_id' => $canonical->id]);
            }
        }

        $duplicate->delete();
    }
}
