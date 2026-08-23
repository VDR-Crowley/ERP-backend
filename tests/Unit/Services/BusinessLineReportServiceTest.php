<?php

namespace Tests\Unit\Services;

use App\Models\Expense;
use App\Models\Flock;
use App\Models\Product;
use App\Models\Sale;
use App\Services\BusinessLineReportService;
use App\Services\ExpenseAllocationService;
use Tests\TestCase;

/**
 * Porte de business-line-report.util.spec.ts (front) pros casos de
 * parseProductComposition/resolveProductComposition/computeEggUnitPrices/
 * allocateSaleAmount/buildBusinessLineReport/buildProductReport/buildSpeciesRevenueSeries.
 * Não usa banco (models montados em memória) — mesma pureza dos utils do front.
 */
class BusinessLineReportServiceTest extends TestCase
{
    private BusinessLineReportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BusinessLineReportService(new ExpenseAllocationService);
    }

    private function product(array $overrides = []): Product
    {
        return new Product(array_merge([
            'unit' => 'Bandeja',
            'unit_price' => 0,
            'stock' => 0,
            'eggs_per_unit' => 0,
        ], $overrides));
    }

    private function sale(Product $product, array $overrides = []): Sale
    {
        $sale = new Sale(array_merge([
            'date' => '2026-07-01',
            'quantity' => 1,
            'unit_price' => 15,
            'total' => 15,
            'payment_pending' => false,
            'buyer' => 'Comprador',
            'delivery_pending' => false,
            'stock_location_type' => 'plantel',
        ], $overrides));
        $sale->setRelation('product', $product);

        return $sale;
    }

    private function expense(array $overrides = []): Expense
    {
        $expense = new Expense(array_merge([
            'date' => '2026-07-01',
            'description' => '',
            'category' => '',
            'amount' => 0,
            'paid' => true,
        ], $overrides));
        $expense->setRelation('speciesOverride', null);

        return $expense;
    }

    private function catalog(): array
    {
        return [
            'bandejaGalinha30' => $this->product(['name' => '1 Bandeja de ovos de galinha', 'unit_price' => 20, 'eggs_per_unit' => 30]),
            'codorna50' => $this->product(['name' => '50 ovos de codorna', 'unit_price' => 15, 'eggs_per_unit' => 50]),
            'galinha30b' => $this->product(['name' => '30 ovos galinha', 'unit_price' => 25, 'eggs_per_unit' => 30]),
            'kitMisto' => $this->product(['name' => '5 ovos Galinha + 50 Codorna', 'unit_price' => 30, 'eggs_per_unit' => 55]),
        ];
    }

    private function flockMock(): array
    {
        return [
            new Flock(['species' => 'Codornas', 'quantity' => 130]),
            new Flock(['species' => 'Galinhas Embrapa 051', 'quantity' => 32]),
        ];
    }

    public function test_parses_mixed_kit_composition(): void
    {
        $this->assertSame(
            ['quail' => 50.0, 'chicken' => 5.0],
            $this->service->parseProductComposition('5 ovos Galinha + 50 Codorna'),
        );
    }

    public function test_parses_single_species_product_with_explicit_number(): void
    {
        $this->assertSame(
            ['quail' => 50.0, 'chicken' => 0.0],
            $this->service->parseProductComposition('50 ovos de codorna'),
        );
    }

    public function test_does_not_confuse_package_count_with_egg_count(): void
    {
        // "1" aqui é a bandeja, não o ovo — não deve virar chicken: 1.
        $this->assertSame(
            ['quail' => 0.0, 'chicken' => 0.0],
            $this->service->parseProductComposition('1 Bandeja de ovos de galinha'),
        );
    }

    public function test_resolve_composition_uses_parsed_name_when_available(): void
    {
        $kit = $this->catalog()['kitMisto'];
        $this->assertSame(['quail' => 50.0, 'chicken' => 5.0], $this->service->resolveProductComposition($kit));
    }

    public function test_resolve_composition_falls_back_to_eggs_per_unit(): void
    {
        $bandeja = $this->catalog()['bandejaGalinha30'];
        $this->assertSame(['quail' => 0.0, 'chicken' => 30.0], $this->service->resolveProductComposition($bandeja));
    }

    public function test_computes_average_egg_unit_price_per_species(): void
    {
        $prices = $this->service->computeEggUnitPrices(collect($this->catalog()));

        $this->assertEqualsWithDelta(15 / 50, $prices['quail'], 1e-5);
        $this->assertEqualsWithDelta((20 + 25) / (30 + 30), $prices['chicken'], 1e-5);
    }

    public function test_egg_unit_price_is_null_without_reference_product(): void
    {
        $this->assertNull($this->service->computeEggUnitPrices(collect())['quail']);
    }

    public function test_allocates_100_percent_to_single_species_product(): void
    {
        $catalog = collect($this->catalog());
        $prices = $this->service->computeEggUnitPrices($catalog);
        $sale = $this->sale($catalog['codorna50'], ['total' => 15]);

        $this->assertSame(['quail' => 15.0, 'chicken' => 0.0], $this->service->allocateSaleAmount($sale, $catalog['codorna50'], $prices));
    }

    public function test_divides_mixed_kit_revenue_by_implied_egg_value(): void
    {
        $catalog = collect($this->catalog());
        $prices = $this->service->computeEggUnitPrices($catalog);
        $sale = $this->sale($catalog['kitMisto'], ['total' => 30]);

        $result = $this->service->allocateSaleAmount($sale, $catalog['kitMisto'], $prices);

        $impliedQuail = 50 * (15 / 50);
        $impliedChicken = 5 * (45 / 60);
        $total = $impliedQuail + $impliedChicken;

        $this->assertEqualsWithDelta(30 * ($impliedQuail / $total), $result['quail'], 1e-5);
        $this->assertEqualsWithDelta(30 * ($impliedChicken / $total), $result['chicken'], 1e-5);
        $this->assertEqualsWithDelta(30, $result['quail'] + $result['chicken'], 1e-5);
    }

    public function test_does_not_classify_product_without_species_mention(): void
    {
        $generic = $this->product(['name' => 'Produto genérico']);
        $sale = $this->sale($generic, ['total' => 10]);

        $this->assertSame(['quail' => 0.0, 'chicken' => 0.0], $this->service->allocateSaleAmount($sale, $generic, ['quail' => null, 'chicken' => null]));
    }

    /**
     * Catálogo só tem produto de referência de codorna (granja só vende ovo de galinha
     * dentro de kits) — sem esse fallback pela contagem crua, o kit inteiro seria jogado
     * 100% pra codorna quando eggUnitPrices.chicken vem null.
     */
    public function test_falls_back_to_raw_egg_count_when_missing_price_reference_for_one_species(): void
    {
        $codorna = $this->product(['name' => '50 ovos de codorna', 'unit_price' => 15, 'eggs_per_unit' => 50]);
        $kit = $this->product(['name' => '5 ovos Galinha + 50 Codorna', 'unit_price' => 30, 'eggs_per_unit' => 55]);
        $prices = $this->service->computeEggUnitPrices(collect(['codorna' => $codorna, 'kit' => $kit]));

        $this->assertNull($prices['chicken']);

        $sale = $this->sale($kit, ['total' => 30]);
        $result = $this->service->allocateSaleAmount($sale, $kit, $prices);

        $this->assertEqualsWithDelta(30 * (50 / 55), $result['quail'], 1e-5);
        $this->assertEqualsWithDelta(30 * (5 / 55), $result['chicken'], 1e-5);
        $this->assertGreaterThan(0, $result['chicken']);
    }

    public function test_business_line_report_aggregates_revenue_cost_profit_margin_by_species(): void
    {
        $catalog = collect($this->catalog());
        $sales = collect([
            $this->sale($catalog['codorna50'], ['total' => 150, 'quantity' => 10]),
            $this->sale($catalog['bandejaGalinha30'], ['total' => 40, 'quantity' => 2]),
        ]);
        $expenses = collect([
            $this->expense(['description' => 'Ração codornas', 'category' => 'Ração', 'amount' => 106]),
            $this->expense(['description' => 'Ração galinhas', 'category' => 'Ração', 'amount' => 200]),
        ]);

        $report = $this->service->buildBusinessLineReport($sales, $expenses, collect($this->flockMock()), $catalog);

        $this->assertSame(150.0, $report['quail']['revenue']);
        $this->assertSame(106.0, $report['quail']['cost']);
        $this->assertSame(44.0, $report['quail']['profit']);

        $this->assertSame(40.0, $report['chicken']['revenue']);
        $this->assertSame(200.0, $report['chicken']['cost']);
        $this->assertSame(-160.0, $report['chicken']['profit']);

        $this->assertFalse($report['chicken_covers_costs']);
        $this->assertSame(160.0, $report['difference_covered_by_quail']);
    }

    public function test_chicken_covers_costs_true_when_chicken_profit_is_non_negative(): void
    {
        $catalog = collect($this->catalog());
        $sales = collect([$this->sale($catalog['bandejaGalinha30'], ['total' => 500, 'quantity' => 25])]);
        $expenses = collect([$this->expense(['description' => 'Ração galinhas', 'category' => 'Ração', 'amount' => 100])]);

        $report = $this->service->buildBusinessLineReport($sales, $expenses, collect($this->flockMock()), $catalog);

        $this->assertTrue($report['chicken_covers_costs']);
        $this->assertSame(0.0, $report['difference_covered_by_quail']);
    }

    /**
     * Custo total (quail + chicken) tem que bater exatamente com a soma bruta das despesas
     * do período — garante que nenhum centavo se perde/duplica no rateio.
     */
    public function test_total_cost_matches_raw_expense_sum(): void
    {
        $catalog = collect($this->catalog());
        $sales = collect([
            $this->sale($catalog['codorna50'], ['total' => 150, 'quantity' => 10]),
            $this->sale($catalog['bandejaGalinha30'], ['total' => 40, 'quantity' => 2]),
        ]);
        $expenses = collect([
            $this->expense(['description' => 'Ração codornas', 'category' => 'Ração', 'amount' => 106]),
            $this->expense(['description' => 'Ração galinhas', 'category' => 'Ração', 'amount' => 200]),
            $this->expense(['description' => 'Tela de proteção', 'category' => 'Tela', 'amount' => 57.3]),
            $this->expense(['description' => 'Ração galinhas Embrapa', 'category' => 'Codornas', 'amount' => 84.9]),
        ]);
        $rawTotal = $expenses->sum('amount');

        $report = $this->service->buildBusinessLineReport($sales, $expenses, collect($this->flockMock()), $catalog);

        $this->assertEqualsWithDelta((float) $rawTotal, $report['quail']['cost'] + $report['chicken']['cost'], 1e-8);
    }

    public function test_product_report_computes_revenue_cost_margin_volume_sorted_by_margin(): void
    {
        $catalog = collect($this->catalog());
        $sales = collect([
            $this->sale($catalog['codorna50'], ['total' => 150, 'quantity' => 10]),
            $this->sale($catalog['bandejaGalinha30'], ['total' => 40, 'quantity' => 2]),
        ]);
        $expenses = collect([
            $this->expense(['description' => 'Ração codornas', 'category' => 'Ração', 'amount' => 106]),
            $this->expense(['description' => 'Ração galinhas', 'category' => 'Ração', 'amount' => 200]),
        ]);

        $results = $this->service->buildProductReport($sales, $expenses, collect($this->flockMock()), $catalog);

        $this->assertCount(2, $results);
        $byName = collect($results)->keyBy('name');

        $this->assertSame(150.0, $byName['50 ovos de codorna']['revenue']);
        $this->assertSame(106.0, $byName['50 ovos de codorna']['cost']);
        $this->assertSame(10, $byName['50 ovos de codorna']['quantity_sold']);

        $this->assertSame(40.0, $byName['1 Bandeja de ovos de galinha']['revenue']);
        $this->assertSame(200.0, $byName['1 Bandeja de ovos de galinha']['cost']);
        $this->assertSame(2, $byName['1 Bandeja de ovos de galinha']['quantity_sold']);

        // Pior margem (galinha, negativa) vem primeiro.
        $this->assertSame('1 Bandeja de ovos de galinha', $results[0]['name']);
    }

    /**
     * Cenário calcado no relato real: Categoria é o TIPO de despesa (Ração, Tela, Feno),
     * não a espécie — só a Descrição indica a espécie nesses casos. Reproduz múltiplos
     * produtos por espécie pra garantir que o costRate de uma espécie não vaza pra outra.
     */
    public function test_product_report_keeps_cost_rate_independent_per_species_with_generic_categories(): void
    {
        $flock = collect([
            new Flock(['species' => 'Codornas', 'quantity' => 100]),
            new Flock(['species' => 'Galinhas Embrapa 051', 'quantity' => 50]),
        ]);
        $products = collect([
            'codornaA' => $this->product(['name' => 'Ovos de codorna A', 'unit_price' => 10, 'eggs_per_unit' => 10]),
            'codornaB' => $this->product(['name' => 'Ovos de codorna B', 'unit_price' => 10, 'eggs_per_unit' => 10]),
            'galinhaA' => $this->product(['name' => 'Ovos de galinha A', 'unit_price' => 10, 'eggs_per_unit' => 10]),
            'galinhaB' => $this->product(['name' => 'Ovos de galinha B', 'unit_price' => 10, 'eggs_per_unit' => 10]),
        ]);
        $sales = collect([
            $this->sale($products['codornaA'], ['date' => '2026-06-01', 'total' => 100]),
            $this->sale($products['codornaB'], ['date' => '2026-07-01', 'total' => 200]),
            $this->sale($products['galinhaA'], ['date' => '2026-06-15', 'total' => 300]),
            $this->sale($products['galinhaB'], ['date' => '2026-07-15', 'total' => 400]),
        ]);
        $expenses = collect([
            $this->expense(['description' => 'Ração galinhas Embrapa', 'category' => 'Ração', 'amount' => 150]),
            $this->expense(['description' => 'Ração codornas', 'category' => 'Ração', 'amount' => 60]),
            $this->expense(['description' => 'Compra de insumo mensal', 'category' => 'Galinha', 'amount' => 40]),
            $this->expense(['description' => 'Tela de proteção', 'category' => 'Tela', 'amount' => 90]),
            $this->expense(['description' => 'Feno', 'category' => 'Feno', 'amount' => 30]),
        ]);

        $results = collect($this->service->buildProductReport($sales, $expenses, $flock, $products));

        $costQuail = $results->filter(fn ($r) => str_starts_with($r['name'], 'Ovos de codorna'))->sum('cost');
        $costChicken = $results->filter(fn ($r) => str_starts_with($r['name'], 'Ovos de galinha'))->sum('cost');

        // codorna: 60 (Ração codornas) + 2/3 de (90 + 30) = 140
        // galinha: 150 (Ração galinhas) + 40 (categoria) + 1/3 de (90 + 30) = 230
        $this->assertEqualsWithDelta(140, $costQuail, 1e-5);
        $this->assertEqualsWithDelta(230, $costChicken, 1e-5);

        $byName = $results->keyBy('name');
        $this->assertEqualsWithDelta(300 * (230 / 700), $byName['Ovos de galinha A']['cost'], 1e-5);
        $this->assertEqualsWithDelta(400 * (230 / 700), $byName['Ovos de galinha B']['cost'], 1e-5);
        $this->assertEqualsWithDelta(100 * (140 / 300), $byName['Ovos de codorna A']['cost'], 1e-5);
        $this->assertEqualsWithDelta(200 * (140 / 300), $byName['Ovos de codorna B']['cost'], 1e-5);
    }

    public function test_species_revenue_series_aggregates_by_month_only_with_sales(): void
    {
        $catalog = collect($this->catalog());
        $sales = collect([
            $this->sale($catalog['codorna50'], ['date' => '2026-06-15', 'total' => 15]),
            $this->sale($catalog['codorna50'], ['date' => '2026-07-01', 'total' => 30]),
            $this->sale($catalog['bandejaGalinha30'], ['date' => '2026-07-02', 'total' => 20]),
        ]);

        $series = $this->service->buildSpeciesRevenueSeries($sales, $catalog);

        $this->assertSame([
            ['month' => '2026-06', 'quail_revenue' => 15.0, 'chicken_revenue' => 0.0],
            ['month' => '2026-07', 'quail_revenue' => 30.0, 'chicken_revenue' => 20.0],
        ], $series);
    }
}
