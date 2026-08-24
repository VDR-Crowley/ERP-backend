<?php

namespace App\Console\Commands;

use App\Models\FeedOpenLog;
use App\Models\FeedStock;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Repara duplicata real de produção no "Histórico de sacos abertos": o
 * endpoint `POST /feed-stocks/{feed_stock}/open-bag` não tinha nenhuma trava
 * contra reenvio (double-click/retry no front), então cada clique repetido
 * criava um `feed_open_log` novo e decrementava `bags_in_stock`/`kg_in_stock`
 * de novo (`FeedStockService::openBag`). A trava adicionada em
 * `OpenFeedStockBagRequest` (mesma chave de negócio abaixo) impede novas
 * duplicatas; este comando limpa as que já existem.
 *
 * Chave de negócio: date + feed_type + weight_kg (mesmo texto cru usado por
 * `ImportPlanilha::importRacaoSacosAbertos()`/migration de `feed_open_logs` —
 * não depende de `feed_stock_id`, que pode ser nulo quando o texto não bate
 * com nenhum `feed_stocks.type`). Dentro de cada grupo duplicado, mantém o
 * registro de menor id (mais antigo) e remove o resto, devolvendo ao
 * `feed_stock` (quando `feed_stock_id` não é nulo) o saco/peso que cada
 * duplicata removida decrementou na criação.
 *
 * Roda em modo dry-run por padrão (só mostra o que seria feito); só altera
 * dado/estoque com `--force`. Idempotente: depois de um `--force`
 * bem-sucedido, rodar de novo não acha mais grupos duplicados.
 */
class DeduplicateFeedOpenLogs extends Command
{
    protected $signature = 'feed-open-logs:deduplicate {--force : Executa de verdade (remove duplicatas e devolve estoque); sem essa flag só mostra o que seria feito}';

    protected $description = 'Remove sacos abertos duplicados (reenvio de open-bag) e devolve o estoque debitado a mais por eles';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        $duplicateGroups = FeedOpenLog::query()
            ->orderBy('id')
            ->get()
            ->groupBy(fn (FeedOpenLog $log) => implode('|', [
                $log->date->toDateString(),
                $log->feed_type,
                (string) $log->weight_kg,
            ]))
            ->filter(fn (Collection $group) => $group->count() > 1);

        if ($duplicateGroups->isEmpty()) {
            $this->info('Nenhum saco aberto duplicado encontrado.');

            return self::SUCCESS;
        }

        $feedStocks = FeedStock::query()->get()->keyBy('id');
        $removedIds = [];
        $rows = [];
        /** @var array<int, array{bags: int, kg: float}> feed_stock_id => estoque a restaurar/restaurado */
        $stockByFeedStock = [];

        foreach ($duplicateGroups as $group) {
            $kept = $group->sortBy('id')->first();

            foreach ($group->reject(fn (FeedOpenLog $log) => $log->id === $kept->id) as $duplicate) {
                $removedIds[] = $duplicate->id;

                $rows[] = [
                    $duplicate->id,
                    $kept->id,
                    $feedStocks->get($duplicate->feed_stock_id)?->type ?? $duplicate->feed_type,
                    $duplicate->date->toDateString(),
                    (string) $duplicate->weight_kg,
                    $duplicate->feed_stock_id ?? '—',
                ];

                if ($duplicate->feed_stock_id !== null) {
                    $current = $stockByFeedStock[$duplicate->feed_stock_id] ?? ['bags' => 0, 'kg' => 0.0];
                    $stockByFeedStock[$duplicate->feed_stock_id] = [
                        'bags' => $current['bags'] + 1,
                        'kg' => $current['kg'] + (float) $duplicate->weight_kg,
                    ];
                }

                if ($force) {
                    DB::transaction(function () use ($duplicate): void {
                        // Delta positivo: desfaz a baixa que essa duplicata causou na criação (FeedStockService::openBag()).
                        if ($duplicate->feed_stock_id !== null) {
                            FeedStock::query()
                                ->whereKey($duplicate->feed_stock_id)
                                ->increment('bags_in_stock', 1);

                            FeedStock::query()
                                ->whereKey($duplicate->feed_stock_id)
                                ->increment('kg_in_stock', (float) $duplicate->weight_kg);
                        }

                        $duplicate->delete();
                    });
                }
            }
        }

        $this->table(['ID removido', 'ID mantido', 'Tipo', 'Data', 'Peso (kg)', 'feed_stock_id'], $rows);

        $this->newLine();
        $this->table(
            ['feed_stock_id', 'Tipo', $force ? 'Sacos restaurados' : 'Sacos a restaurar', $force ? 'Kg restaurado' : 'Kg a restaurar'],
            collect($stockByFeedStock)
                ->map(fn (array $qty, int $feedStockId) => [
                    $feedStockId,
                    $feedStocks->get($feedStockId)?->type ?? "feed_stock #{$feedStockId}",
                    $qty['bags'],
                    number_format($qty['kg'], 2, '.', ''),
                ])
                ->values()
                ->all()
        );

        $totalGroups = $duplicateGroups->count();
        $totalRemoved = count($removedIds);
        $skippedNullFk = collect($rows)->filter(fn (array $row) => $row[5] === '—')->count();

        $summary = $force
            ? "Removido(s) {$totalRemoved} saco(s) aberto(s) duplicado(s) em {$totalGroups} grupo(s); estoque restaurado."
            : "[DRY-RUN] {$totalRemoved} saco(s) aberto(s) duplicado(s) em {$totalGroups} grupo(s) seriam removidos; nada foi alterado. Rode com --force para executar de verdade.";

        $this->info($summary);

        if ($skippedNullFk > 0) {
            $this->warn("{$skippedNullFk} duplicata(s) sem feed_stock_id (texto não bate com nenhum feed_stocks.type) — removida(s) sem restaurar estoque, pois nunca decrementaram nenhum saldo rastreável.");
        }

        Log::info('feed-open-logs:deduplicate executado', [
            'force' => $force,
            'groups' => $totalGroups,
            'removed_ids' => $removedIds,
            'stock_restored_by_feed_stock' => $stockByFeedStock,
        ]);

        return self::SUCCESS;
    }
}
