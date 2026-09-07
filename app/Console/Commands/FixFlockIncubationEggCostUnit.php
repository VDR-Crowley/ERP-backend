<?php

namespace App\Console\Commands;

use App\Models\FlockIncubation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Repara o bug real de produção (2026-09-07): o front-end (commit `4bd8e24`
 * em ERP-front) mudou o significado de "Custo dos ovos" de CUSTO TOTAL do
 * lote pra PREÇO POR OVO — `Investimento` agora é calculado como
 * `egg_count * egg_cost + feed_cost`. Todo lote pré-existente ainda guarda
 * `egg_cost` no sentido antigo (total), o que infla o Investimento exibido
 * em `egg_count` vezes (ex.: 250 ovos, custo total R$75,80, exibindo como
 * R$18.950,00).
 *
 * Converte `egg_cost` de total pra unitário: `novo = antigo / egg_count`.
 * Pula (sem alterar) `egg_count == 0` (divisão por zero) e qualquer linha
 * cujo `egg_cost` já caia sozinho numa faixa plausível de preço por ovo
 * (R$0,20–R$1,00) — nesse caso não dá pra saber se já é unitário ou se é um
 * total pequeno por coincidência, e converter às cegas arriscaria uma dupla
 * conversão; a linha é reportada como ambígua em vez de decidida por
 * adivinhação.
 *
 * Roda em modo dry-run por padrão; só altera dado com `--force`. Idempotente
 * na única direção que importa: depois de convertido, o valor unitário real
 * cai na faixa plausível e passa a ser pulado como ambíguo por linhas
 * seguintes do comando (não há uma segunda conversão automática).
 */
class FixFlockIncubationEggCostUnit extends Command
{
    private const MIN_PLAUSIBLE_PER_EGG = 0.20;

    private const MAX_PLAUSIBLE_PER_EGG = 1.00;

    protected $signature = 'flock-incubations:fix-egg-cost-unit {--force : Executa de verdade (grava o valor convertido); sem essa flag só mostra o que seria feito}';

    protected $description = 'Converte egg_cost de custo total do lote pra custo por ovo, revertendo a mudança de semântica do front-end';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        $incubations = FlockIncubation::query()
            ->whereNotNull('egg_cost')
            ->orderBy('id')
            ->get(['id', 'egg_count', 'egg_cost']);

        if ($incubations->isEmpty()) {
            $this->info('Nenhum lote com egg_cost preenchido.');

            return self::SUCCESS;
        }

        $rows = [];
        $converted = [];
        $skippedZero = [];
        $skippedAmbiguous = [];

        foreach ($incubations as $incubation) {
            $oldCost = (float) $incubation->egg_cost;

            if ($incubation->egg_count === 0) {
                $skippedZero[] = $incubation->id;
                $rows[] = [$incubation->id, $incubation->egg_count, number_format($oldCost, 2, ',', '.'), 'PULADO (divisão por zero)'];

                continue;
            }

            if ($oldCost >= self::MIN_PLAUSIBLE_PER_EGG && $oldCost <= self::MAX_PLAUSIBLE_PER_EGG) {
                $skippedAmbiguous[] = $incubation->id;
                $rows[] = [$incubation->id, $incubation->egg_count, number_format($oldCost, 2, ',', '.'), 'PULADO (ambíguo — já parece valor por ovo)'];

                continue;
            }

            $newCost = round($oldCost / $incubation->egg_count, 2);
            $converted[$incubation->id] = ['old' => $oldCost, 'new' => $newCost];
            $rows[] = [$incubation->id, $incubation->egg_count, number_format($oldCost, 2, ',', '.'), number_format($newCost, 2, ',', '.')];

            if ($force) {
                $incubation->update(['egg_cost' => $newCost]);
            }
        }

        $this->table(['ID', 'Qtd. ovos', 'egg_cost antigo (total)', $force ? 'egg_cost novo (por ovo)' : 'egg_cost novo (por ovo, previsto)'], $rows);

        $this->newLine();

        if (! empty($skippedZero)) {
            $this->warn('Pulado(s) por divisão por zero (egg_count = 0): '.implode(', ', $skippedZero));
        }

        if (! empty($skippedAmbiguous)) {
            $this->warn('Pulado(s) por ambiguidade (egg_cost já numa faixa plausível de preço por ovo, R$0,20–R$1,00): '.implode(', ', $skippedAmbiguous));
        }

        $totalConverted = count($converted);

        $summary = $force
            ? "Convertido(s) {$totalConverted} lote(s) de custo total pra custo por ovo."
            : "[DRY-RUN] {$totalConverted} lote(s) seriam convertidos de custo total pra custo por ovo; nada foi alterado. Rode com --force para executar de verdade.";

        $this->info($summary);

        Log::info('flock-incubations:fix-egg-cost-unit executado', [
            'force' => $force,
            'converted' => $converted,
            'skipped_zero_ids' => $skippedZero,
            'skipped_ambiguous_ids' => $skippedAmbiguous,
        ]);

        return self::SUCCESS;
    }
}
