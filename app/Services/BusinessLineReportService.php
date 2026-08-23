<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Flock;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Support\Collection;

/**
 * Porte fiel de `buildBusinessLineReport`/`buildProductReport`/
 * `buildSpeciesRevenueSeries`/`allocateSaleAmount`/`parseProductComposition`/
 * `resolveProductComposition`/`computeEggUnitPrices` (business-line-report.util.ts
 * no front) — relatório "Análise por Linha de Negócio" (codorna/quail x
 * galinha/chicken), agora calculado no backend a partir de `sales`, `expenses`
 * (excluindo `sale_exclusions` e respeitando `expense_species_overrides`),
 * `flock` e `products`.
 */
class BusinessLineReportService
{
    /**
     * Casa "50 ovos de codorna", "5 ovos Galinha + 50 Codorna" etc.: um número
     * seguido, a até 12 caracteres de distância, pela palavra da espécie. A
     * janela curta evita casar dígitos que não são contagem de ovos (ex.: "1
     * Bandeja de ovos de galinha", onde "1" é a bandeja, não o ovo).
     */
    private const COMPOSITION_REGEX = '/(\d+(?:[.,]\d+)?)[^\d]{0,12}?(codorna|galinha)/iu';

    public function __construct(private readonly ExpenseAllocationService $expenseAllocation) {}

    /**
     * Monta o relatório completo pro período já filtrado pelo chamador
     * (controller resolve `sales`/`expenses` com o range de datas e sem as
     * vendas marcadas em `sale_exclusions`).
     *
     * @param  Collection<int, Sale>  $sales
     * @param  Collection<int, Expense>  $expenses
     * @param  Collection<int, Flock>  $flock
     * @param  Collection<int, Product>  $products
     * @return array{
     *     by_species: array,
     *     by_product: list<array>,
     *     monthly_series: list<array>,
     * }
     */
    public function build(Collection $sales, Collection $expenses, Collection $flock, Collection $products): array
    {
        return [
            'by_species' => $this->buildBusinessLineReport($sales, $expenses, $flock, $products),
            'by_product' => $this->buildProductReport($sales, $expenses, $flock, $products),
            'monthly_series' => $this->buildSpeciesRevenueSeries($sales, $products),
        ];
    }

    /** @return array{codorna: float, galinha: float} */
    public function parseProductComposition(string $productName): array
    {
        $result = [ExpenseAllocationService::QUAIL => 0.0, ExpenseAllocationService::CHICKEN => 0.0];

        preg_match_all(self::COMPOSITION_REGEX, $productName, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $quantity = (float) str_replace(',', '.', $match[1]);
            $species = str_starts_with(mb_strtolower($match[2]), 'codorna')
                ? ExpenseAllocationService::QUAIL
                : ExpenseAllocationService::CHICKEN;
            $result[$species] += $quantity;
        }

        return $result;
    }

    /** @return array{quail: float, chicken: float} */
    public function resolveProductComposition(Product $product): array
    {
        $parsed = $this->parseProductComposition($product->name);
        if ($parsed[ExpenseAllocationService::QUAIL] > 0 || $parsed[ExpenseAllocationService::CHICKEN] > 0) {
            return $parsed;
        }

        $mentions = $this->expenseAllocation->detectSpeciesMentions($product->name);
        if (count($mentions) === 1) {
            return $mentions[0] === ExpenseAllocationService::QUAIL
                ? [ExpenseAllocationService::QUAIL => (float) $product->eggs_per_unit, ExpenseAllocationService::CHICKEN => 0.0]
                : [ExpenseAllocationService::QUAIL => 0.0, ExpenseAllocationService::CHICKEN => (float) $product->eggs_per_unit];
        }

        return [ExpenseAllocationService::QUAIL => 0.0, ExpenseAllocationService::CHICKEN => 0.0];
    }

    /**
     * Preço médio implícito por ovo de cada espécie, a partir dos produtos de
     * espécie única do catálogo. `null` quando não há produto de referência.
     *
     * @param  Collection<int, Product>  $products
     * @return array{quail: float|null, chicken: float|null}
     */
    public function computeEggUnitPrices(Collection $products): array
    {
        $totals = [
            ExpenseAllocationService::QUAIL => ['value' => 0.0, 'eggs' => 0],
            ExpenseAllocationService::CHICKEN => ['value' => 0.0, 'eggs' => 0],
        ];

        foreach ($products as $product) {
            $mentions = $this->expenseAllocation->detectSpeciesMentions($product->name);
            if (count($mentions) !== 1 || $product->eggs_per_unit <= 0) {
                continue;
            }
            $species = $mentions[0];
            $totals[$species]['value'] += (float) $product->unit_price;
            $totals[$species]['eggs'] += $product->eggs_per_unit;
        }

        return [
            ExpenseAllocationService::QUAIL => $totals[ExpenseAllocationService::QUAIL]['eggs'] > 0
                ? $totals[ExpenseAllocationService::QUAIL]['value'] / $totals[ExpenseAllocationService::QUAIL]['eggs']
                : null,
            ExpenseAllocationService::CHICKEN => $totals[ExpenseAllocationService::CHICKEN]['eggs'] > 0
                ? $totals[ExpenseAllocationService::CHICKEN]['value'] / $totals[ExpenseAllocationService::CHICKEN]['eggs']
                : null,
        ];
    }

    /**
     * Rateia o valor de uma venda entre as espécies: 100% pra espécie única
     * do produto, dividido pelo valor implícito de cada tipo de ovo quando o
     * produto é misto (com referência de preço pras duas espécies), ou pela
     * contagem crua de ovos do kit quando falta referência de preço de uma
     * delas (nunca joga 100% pra quem tem referência). Produto sem nenhuma
     * menção reconhecível não entra no rateio.
     *
     * @param  array{quail: float|null, chicken: float|null}  $eggUnitPrices
     * @return array{quail: float, chicken: float}
     */
    public function allocateSaleAmount(Sale $sale, ?Product $product, array $eggUnitPrices): array
    {
        $zero = [ExpenseAllocationService::QUAIL => 0.0, ExpenseAllocationService::CHICKEN => 0.0];
        if ($product === null) {
            return $zero;
        }

        $total = (float) $sale->total;
        $mentions = $this->expenseAllocation->detectSpeciesMentions($product->name);

        if (count($mentions) === 1) {
            return $mentions[0] === ExpenseAllocationService::QUAIL
                ? [ExpenseAllocationService::QUAIL => $total, ExpenseAllocationService::CHICKEN => 0.0]
                : [ExpenseAllocationService::QUAIL => 0.0, ExpenseAllocationService::CHICKEN => $total];
        }

        if (count($mentions) === 2) {
            $composition = $this->resolveProductComposition($product);
            $quailPrice = $eggUnitPrices[ExpenseAllocationService::QUAIL];
            $chickenPrice = $eggUnitPrices[ExpenseAllocationService::CHICKEN];

            if ($quailPrice !== null && $chickenPrice !== null) {
                $impliedQuail = $composition[ExpenseAllocationService::QUAIL] * $quailPrice;
                $impliedChicken = $composition[ExpenseAllocationService::CHICKEN] * $chickenPrice;
                $impliedTotal = $impliedQuail + $impliedChicken;
                if ($impliedTotal > 0) {
                    return [
                        ExpenseAllocationService::QUAIL => $total * ($impliedQuail / $impliedTotal),
                        ExpenseAllocationService::CHICKEN => $total * ($impliedChicken / $impliedTotal),
                    ];
                }
            }

            $eggTotal = $composition[ExpenseAllocationService::QUAIL] + $composition[ExpenseAllocationService::CHICKEN];
            if ($eggTotal > 0) {
                return [
                    ExpenseAllocationService::QUAIL => $total * ($composition[ExpenseAllocationService::QUAIL] / $eggTotal),
                    ExpenseAllocationService::CHICKEN => $total * ($composition[ExpenseAllocationService::CHICKEN] / $eggTotal),
                ];
            }
        }

        return $zero;
    }

    /**
     * @param  Collection<int, Sale>  $sales
     * @param  Collection<int, Expense>  $expenses
     * @param  Collection<int, Flock>  $flock
     * @param  Collection<int, Product>  $products
     */
    public function buildBusinessLineReport(Collection $sales, Collection $expenses, Collection $flock, Collection $products): array
    {
        $flockRatio = $this->expenseAllocation->computeFlockRatio($flock);
        $eggUnitPrices = $this->computeEggUnitPrices($products);

        $revenue = [ExpenseAllocationService::QUAIL => 0.0, ExpenseAllocationService::CHICKEN => 0.0];
        foreach ($sales as $sale) {
            $alloc = $this->allocateSaleAmount($sale, $sale->product, $eggUnitPrices);
            $revenue[ExpenseAllocationService::QUAIL] += $alloc[ExpenseAllocationService::QUAIL];
            $revenue[ExpenseAllocationService::CHICKEN] += $alloc[ExpenseAllocationService::CHICKEN];
        }

        $cost = [ExpenseAllocationService::QUAIL => 0.0, ExpenseAllocationService::CHICKEN => 0.0];
        foreach ($expenses as $expense) {
            $alloc = $this->expenseAllocation->allocateExpenseAmount($expense, $flockRatio, $expense->speciesOverride);
            $cost[ExpenseAllocationService::QUAIL] += $alloc[ExpenseAllocationService::QUAIL];
            $cost[ExpenseAllocationService::CHICKEN] += $alloc[ExpenseAllocationService::CHICKEN];
        }

        $quail = $this->toSpeciesTotals($revenue[ExpenseAllocationService::QUAIL], $cost[ExpenseAllocationService::QUAIL]);
        $chicken = $this->toSpeciesTotals($revenue[ExpenseAllocationService::CHICKEN], $cost[ExpenseAllocationService::CHICKEN]);
        $chickenCoversCosts = $chicken['profit'] >= 0;

        return [
            'quail' => $quail,
            'chicken' => $chicken,
            'chicken_covers_costs' => $chickenCoversCosts,
            'difference_covered_by_quail' => $chickenCoversCosts ? 0.0 : abs($chicken['profit']),
        ];
    }

    /**
     * Custo por produto = "taxa de custo" da espécie (custo/receita da
     * espécie, do rateio acima) aplicada sobre a fatia de receita do produto
     * naquela espécie — a margem por produto soma de volta pra margem por
     * espécie. Ordenado por margem (pior primeiro).
     *
     * @param  Collection<int, Sale>  $sales
     * @param  Collection<int, Expense>  $expenses
     * @param  Collection<int, Flock>  $flock
     * @param  Collection<int, Product>  $products
     * @return list<array>
     */
    public function buildProductReport(Collection $sales, Collection $expenses, Collection $flock, Collection $products): array
    {
        $flockRatio = $this->expenseAllocation->computeFlockRatio($flock);
        $eggUnitPrices = $this->computeEggUnitPrices($products);

        $revenueByProduct = [];
        $quantityByProduct = [];
        $speciesRevenueByProduct = [];
        $speciesRevenue = [ExpenseAllocationService::QUAIL => 0.0, ExpenseAllocationService::CHICKEN => 0.0];

        foreach ($sales as $sale) {
            $name = $sale->product->name;
            $revenueByProduct[$name] = ($revenueByProduct[$name] ?? 0.0) + (float) $sale->total;
            $quantityByProduct[$name] = ($quantityByProduct[$name] ?? 0) + $sale->quantity;

            $alloc = $this->allocateSaleAmount($sale, $sale->product, $eggUnitPrices);
            $prev = $speciesRevenueByProduct[$name] ?? [ExpenseAllocationService::QUAIL => 0.0, ExpenseAllocationService::CHICKEN => 0.0];
            $prev[ExpenseAllocationService::QUAIL] += $alloc[ExpenseAllocationService::QUAIL];
            $prev[ExpenseAllocationService::CHICKEN] += $alloc[ExpenseAllocationService::CHICKEN];
            $speciesRevenueByProduct[$name] = $prev;
            $speciesRevenue[ExpenseAllocationService::QUAIL] += $alloc[ExpenseAllocationService::QUAIL];
            $speciesRevenue[ExpenseAllocationService::CHICKEN] += $alloc[ExpenseAllocationService::CHICKEN];
        }

        $speciesCost = [ExpenseAllocationService::QUAIL => 0.0, ExpenseAllocationService::CHICKEN => 0.0];
        foreach ($expenses as $expense) {
            $alloc = $this->expenseAllocation->allocateExpenseAmount($expense, $flockRatio, $expense->speciesOverride);
            $speciesCost[ExpenseAllocationService::QUAIL] += $alloc[ExpenseAllocationService::QUAIL];
            $speciesCost[ExpenseAllocationService::CHICKEN] += $alloc[ExpenseAllocationService::CHICKEN];
        }

        $costRate = [
            ExpenseAllocationService::QUAIL => $speciesRevenue[ExpenseAllocationService::QUAIL] > 0
                ? $speciesCost[ExpenseAllocationService::QUAIL] / $speciesRevenue[ExpenseAllocationService::QUAIL] : 0.0,
            ExpenseAllocationService::CHICKEN => $speciesRevenue[ExpenseAllocationService::CHICKEN] > 0
                ? $speciesCost[ExpenseAllocationService::CHICKEN] / $speciesRevenue[ExpenseAllocationService::CHICKEN] : 0.0,
        ];

        $results = [];
        foreach ($revenueByProduct as $name => $revenue) {
            $perSpecies = $speciesRevenueByProduct[$name] ?? [ExpenseAllocationService::QUAIL => 0.0, ExpenseAllocationService::CHICKEN => 0.0];
            $cost = $perSpecies[ExpenseAllocationService::QUAIL] * $costRate[ExpenseAllocationService::QUAIL]
                + $perSpecies[ExpenseAllocationService::CHICKEN] * $costRate[ExpenseAllocationService::CHICKEN];
            $profit = $revenue - $cost;
            $results[] = [
                'name' => $name,
                'revenue' => $revenue,
                'cost' => $cost,
                'profit' => $profit,
                'margin_pct' => $revenue > 0 ? ($profit / $revenue) * 100 : 0.0,
                'quantity_sold' => $quantityByProduct[$name] ?? 0,
            ];
        }

        usort($results, fn (array $a, array $b) => $a['margin_pct'] <=> $b['margin_pct']);

        return $results;
    }

    /**
     * Série mensal de receita por espécie. Só meses com venda real aparecem.
     *
     * @param  Collection<int, Sale>  $sales
     * @param  Collection<int, Product>  $products
     * @return list<array{month: string, quail_revenue: float, chicken_revenue: float}>
     */
    public function buildSpeciesRevenueSeries(Collection $sales, Collection $products): array
    {
        $eggUnitPrices = $this->computeEggUnitPrices($products);
        $byMonth = [];

        foreach ($sales as $sale) {
            $month = substr((string) $sale->date, 0, 7);
            $alloc = $this->allocateSaleAmount($sale, $sale->product, $eggUnitPrices);
            $entry = $byMonth[$month] ?? [ExpenseAllocationService::QUAIL => 0.0, ExpenseAllocationService::CHICKEN => 0.0];
            $entry[ExpenseAllocationService::QUAIL] += $alloc[ExpenseAllocationService::QUAIL];
            $entry[ExpenseAllocationService::CHICKEN] += $alloc[ExpenseAllocationService::CHICKEN];
            $byMonth[$month] = $entry;
        }

        ksort($byMonth);

        $series = [];
        foreach ($byMonth as $month => $totals) {
            $series[] = [
                'month' => $month,
                'quail_revenue' => $totals[ExpenseAllocationService::QUAIL],
                'chicken_revenue' => $totals[ExpenseAllocationService::CHICKEN],
            ];
        }

        return $series;
    }

    private function toSpeciesTotals(float $revenue, float $cost): array
    {
        $profit = $revenue - $cost;

        return [
            'revenue' => $revenue,
            'cost' => $cost,
            'profit' => $profit,
            'margin_pct' => $revenue > 0 ? ($profit / $revenue) * 100 : 0.0,
        ];
    }
}
