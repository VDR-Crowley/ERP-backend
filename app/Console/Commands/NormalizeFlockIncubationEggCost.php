<?php

namespace App\Console\Commands;

use App\Models\FlockIncubation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Repara o bug real de produção (2026-08-30, front commit 4bd8e24): antes
 * desse commit, `egg_cost` era tratado no front como custo TOTAL do lote
 * ("Investimento estimado" = egg_cost + feed_cost, sem multiplicar por
 * egg_count). O commit mudou o significado pra preço POR OVO daqui pra
 * frente ("Investimento estimado" = egg_count × egg_cost + feed_cost), mas
 * não migrou os registros já salvos — cada linha antiga ainda guarda o
 * TOTAL do lote em `egg_cost`, não o preço unitário. Resultado visto por um
 * usuário real: lote de 250 ovos com egg_cost=75.80 (total antigo) exibindo
 * "Investimento até 45 dias: R$18.950,00" (250 × 75.80) em vez de ~R$225-300.
 *
 * Heurística de conversão (sem coluna de "versão do dado" pra diferenciar
 * linha antiga de nova): nenhum preço real de ovo (codorna ou galinha) passa
 * de MAX_PLAUSIVEL_POR_OVO. Se `egg_cost` já está abaixo desse teto, a linha
 * já foi criada/editada com o significado novo (preço unitário) — não mexe.
 * Se `egg_cost` está acima do teto, só faz sentido como TOTAL do lote antigo
 * — converte pra `egg_cost / egg_count`. Isso também torna o comando
 * idempotente: depois de convertida, a linha cai abaixo do teto e some do
 * relatório em execuções seguintes.
 *
 * Roda em modo dry-run por padrão (só mostra o que seria feito); só grava
 * com `--force`.
 */
class NormalizeFlockIncubationEggCost extends Command
{
    protected $signature = 'flock-incubations:normalize-egg-cost {--force : Executa de verdade (grava egg_cost convertido); sem essa flag só mostra o que seria feito}';

    protected $description = 'Converte egg_cost de custo TOTAL do lote (semântica antiga) pra preço POR OVO (semântica atual) nas linhas de flock_incubations já salvas';

    /** Nenhum preço real de ovo passa disso — acima do teto, egg_cost só pode ser o total antigo do lote. */
    private const MAX_PLAUSIVEL_POR_OVO = 5.00;

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        $rows = FlockIncubation::query()
            ->whereNotNull('egg_cost')
            ->orderBy('id')
            ->get();

        $toConvert = [];
        $skippedZeroQty = [];
        $skippedJaUnitario = 0;

        foreach ($rows as $row) {
            $eggCost = (float) $row->egg_cost;

            if ($row->egg_count <= 0) {
                $skippedZeroQty[] = [$row->id, $row->species, $row->egg_count, number_format($eggCost, 2, ',', '.')];

                continue;
            }

            if ($eggCost <= self::MAX_PLAUSIVEL_POR_OVO) {
                $skippedJaUnitario++;

                continue;
            }

            $novo = round($eggCost / $row->egg_count, 2);
            $toConvert[] = [
                'row' => $row,
                'display' => [
                    $row->id,
                    $row->species,
                    $row->egg_count,
                    number_format($eggCost, 2, ',', '.'),
                    number_format($novo, 2, ',', '.'),
                ],
                'novo' => $novo,
            ];
        }

        if (empty($toConvert) && empty($skippedZeroQty)) {
            $this->info("Nenhuma linha com egg_cost acima de R$ " . number_format(self::MAX_PLAUSIVEL_POR_OVO, 2, ',', '.') . " por ovo — nada pra converter ({$skippedJaUnitario} já unitária(s)).");

            return self::SUCCESS;
        }

        if (! empty($toConvert)) {
            $this->table(
                ['ID', 'Espécie', 'Qtd. ovos', 'egg_cost atual (total antigo)', 'egg_cost novo (por ovo)'],
                array_map(fn (array $c) => $c['display'], $toConvert)
            );
        }

        if (! empty($skippedZeroQty)) {
            $this->newLine();
            $this->warn('Puladas (egg_count = 0, não dá pra inferir preço unitário):');
            $this->table(['ID', 'Espécie', 'Qtd. ovos', 'egg_cost atual'], $skippedZeroQty);
        }

        $convertedIds = [];

        if ($force) {
            foreach ($toConvert as $c) {
                $c['row']->update(['egg_cost' => $c['novo']]);
                $convertedIds[] = $c['row']->id;
            }
        }

        $this->newLine();
        $summary = $force
            ? 'Convertida(s) ' . count($toConvert) . ' linha(s); ' . count($skippedZeroQty) . ' pulada(s) por egg_count=0; ' . $skippedJaUnitario . ' já estava(m) em preço unitário.'
            : '[DRY-RUN] ' . count($toConvert) . ' linha(s) seriam convertidas; ' . count($skippedZeroQty) . ' pulada(s) por egg_count=0; ' . $skippedJaUnitario . ' já em preço unitário. Nada foi alterado. Rode com --force para executar de verdade.';

        $this->info($summary);

        Log::info('flock-incubations:normalize-egg-cost executado', [
            'force' => $force,
            'converted_ids' => $convertedIds,
            'skipped_zero_qty_ids' => array_column($skippedZeroQty, 0),
            'already_unit_count' => $skippedJaUnitario,
        ]);

        return self::SUCCESS;
    }
}
