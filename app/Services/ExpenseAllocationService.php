<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseSpeciesOverride;
use App\Models\Flock;
use Illuminate\Support\Collection;

/**
 * Porte fiel de `allocateExpenseAmount`/`computeFlockRatio`/`classifyFlockSpecies`
 * (business-line-report.util.ts no front). Espécie é detectada por texto livre em
 * português ("codorna"/"galinha" — mesma fonte de dado real usada em `flock.species`,
 * nome de produto e descrição/categoria de despesa); o resultado usa o vocabulário
 * de enum do backend (`quail`/`chicken`, ver commit "unifica vocabulário de espécie").
 *
 * Critério (validado em planilha com o usuário, replicado do front):
 * - Categoria menciona espécie única -> 100% pra ela.
 * - Sem menção na categoria, descrição menciona espécie única -> 100% pra ela
 *   (categoria tem prioridade sobre descrição — é o campo que o usuário edita
 *   deliberadamente pra corrigir a classificação).
 * - Nenhuma das duas identifica espécie única (sem menção, ou menciona as duas
 *   ao mesmo tempo) -> rateado pelo tamanho real do plantel (`computeFlockRatio`).
 * - `expense_species_overrides`, quando existe pra despesa, tem prioridade sobre
 *   tudo isso: `species` null força o rateio pelo plantel mesmo que o texto
 *   identifique espécie única; `species` preenchida força 100% pra ela.
 */
class ExpenseAllocationService
{
    public const QUAIL = 'quail';

    public const CHICKEN = 'chicken';

    /**
     * Menções de espécie no texto, ordenadas pela posição em que aparecem
     * (não uma ordem fixa) — importante pra identificar qual espécie "vem
     * primeiro" num texto que menciona as duas.
     *
     * @return list<string>
     */
    public function detectSpeciesMentions(string $text): array
    {
        $lower = mb_strtolower($text);
        $codornaIndex = mb_strpos($lower, 'codorna');
        $galinhaIndex = mb_strpos($lower, 'galinha');

        $mentions = [];
        if ($codornaIndex !== false) {
            $mentions[] = ['species' => self::QUAIL, 'index' => $codornaIndex];
        }
        if ($galinhaIndex !== false) {
            $mentions[] = ['species' => self::CHICKEN, 'index' => $galinhaIndex];
        }

        usort($mentions, fn (array $a, array $b) => $a['index'] <=> $b['index']);

        return array_column($mentions, 'species');
    }

    public function classifyFlockSpecies(string $speciesLabel): ?string
    {
        $mentions = $this->detectSpeciesMentions($speciesLabel);

        return count($mentions) === 1 ? $mentions[0] : null;
    }

    /**
     * Proporção real do plantel (tamanho, não percentual fixo) entre as duas
     * espécies. Sem plantel classificável, cai num rateio neutro 50/50.
     *
     * @param  Collection<int, Flock>  $flock
     * @return array{quail: float, chicken: float}
     */
    public function computeFlockRatio(Collection $flock): array
    {
        $totals = [self::QUAIL => 0, self::CHICKEN => 0];

        foreach ($flock as $item) {
            $species = $this->classifyFlockSpecies($item->species);
            if ($species !== null) {
                $totals[$species] += $item->quantity;
            }
        }

        $sum = $totals[self::QUAIL] + $totals[self::CHICKEN];
        if ($sum <= 0) {
            return [self::QUAIL => 0.5, self::CHICKEN => 0.5];
        }

        return [
            self::QUAIL => $totals[self::QUAIL] / $sum,
            self::CHICKEN => $totals[self::CHICKEN] / $sum,
        ];
    }

    /**
     * @param  array{quail: float, chicken: float}  $flockRatio
     * @param  ExpenseSpeciesOverride|null  $override  Ausente (null) mantém a
     *                                                 detecção automática; presente com `species` null força o rateio pelo
     *                                                 plantel; presente com `species` preenchida força 100% pra ela.
     * @return array{quail: float, chicken: float}
     */
    public function allocateExpenseAmount(Expense $expense, array $flockRatio, ?ExpenseSpeciesOverride $override): array
    {
        $amount = (float) $expense->amount;

        if ($override !== null) {
            if ($override->species === null) {
                return $this->splitByRatio($amount, $flockRatio);
            }

            return $this->allTo($override->species, $amount);
        }

        $categoryMentions = $this->detectSpeciesMentions($expense->category ?? '');
        $descriptionMentions = $this->detectSpeciesMentions($expense->description ?? '');

        $species = match (true) {
            count($categoryMentions) === 1 => $categoryMentions[0],
            count($descriptionMentions) === 1 => $descriptionMentions[0],
            default => null,
        };

        if ($species !== null) {
            return $this->allTo($species, $amount);
        }

        return $this->splitByRatio($amount, $flockRatio);
    }

    /** @return array{quail: float, chicken: float} */
    private function allTo(string $species, float $amount): array
    {
        return $species === self::QUAIL
            ? [self::QUAIL => $amount, self::CHICKEN => 0.0]
            : [self::QUAIL => 0.0, self::CHICKEN => $amount];
    }

    /**
     * @param  array{quail: float, chicken: float}  $ratio
     * @return array{quail: float, chicken: float}
     */
    private function splitByRatio(float $amount, array $ratio): array
    {
        return [
            self::QUAIL => $amount * $ratio[self::QUAIL],
            self::CHICKEN => $amount * $ratio[self::CHICKEN],
        ];
    }
}
