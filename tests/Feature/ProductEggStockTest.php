<?php

namespace Tests\Feature;

use App\Models\DailyProduction;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Vendedor;
use App\Services\StockLocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * `Product::stock()`: estoque calculado do Plantel pra produtos de ovo
 * mapeados (`egg_species`), a partir de produção real + vendas/transferências
 * reais. Produto sem mapeamento continua com a coluna crua de sempre.
 */
class ProductEggStockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(), ['access']);
    }

    public function test_product_without_egg_species_keeps_raw_stock_column(): void
    {
        $product = Product::factory()->create(['stock' => 42, 'egg_species' => null]);

        DailyProduction::factory()->create(['chicken_eggs' => 999, 'quail_eggs' => 999, 'is_mock' => false]);

        $this->assertSame(42, $product->fresh()->stock);
    }

    public function test_egg_product_stock_is_production_minus_sales(): void
    {
        DailyProduction::factory()->create(['date' => '2026-07-01', 'chicken_eggs' => 300, 'quail_eggs' => 0, 'is_mock' => false]);

        $product = Product::factory()->create([
            'eggs_per_unit' => 30,
            'egg_species' => 'chicken',
            'stock' => 0,
        ]);

        // 300 ovos / 30 por unidade = 10 unidades produzidas.
        $this->assertSame(10, $product->fresh()->stock);

        Sale::factory()->create([
            'product_id' => $product->id,
            'quantity' => 4,
            'stock_location_type' => 'plantel',
            'stock_location_vendedor_id' => null,
            'is_mock' => false,
        ]);

        $this->assertSame(6, $product->fresh()->stock);
    }

    public function test_egg_product_stock_floors_the_accumulated_production_not_each_event(): void
    {
        // 25 + 25 = 50 ovos acumulados / 30 = 1 unidade. Um contador que
        // fizesse floor(25/30) + floor(25/30) por evento daria 0 — o
        // calculado bate com a soma bruta.
        DailyProduction::factory()->create(['date' => '2026-07-01', 'chicken_eggs' => 25, 'quail_eggs' => 0, 'is_mock' => false]);
        DailyProduction::factory()->create(['date' => '2026-07-02', 'chicken_eggs' => 25, 'quail_eggs' => 0, 'is_mock' => false]);

        $product = Product::factory()->create(['eggs_per_unit' => 30, 'egg_species' => 'chicken']);

        $this->assertSame(1, $product->fresh()->stock);
    }

    public function test_egg_product_stock_can_go_negative_when_sales_exceed_production(): void
    {
        DailyProduction::factory()->create(['chicken_eggs' => 30, 'quail_eggs' => 0, 'is_mock' => false]);

        $product = Product::factory()->create(['eggs_per_unit' => 30, 'egg_species' => 'chicken']);

        Sale::factory()->create([
            'product_id' => $product->id,
            'quantity' => 5,
            'stock_location_type' => 'plantel',
            'stock_location_vendedor_id' => null,
            'is_mock' => false,
        ]);

        // 1 unidade produzida - 5 vendidas = -4. Permitido, é dado real de negócio.
        $this->assertSame(-4, $product->fresh()->stock);
    }

    public function test_egg_product_stock_accounts_for_transfers_into_and_out_of_plantel(): void
    {
        DailyProduction::factory()->create(['quail_eggs' => 500, 'chicken_eggs' => 0, 'is_mock' => false]);
        $product = Product::factory()->create(['eggs_per_unit' => 50, 'egg_species' => 'quail']);
        $vendedor = Vendedor::factory()->create();

        // 500/50 = 10 produzidas.
        $this->assertSame(10, $product->fresh()->stock);

        StockTransfer::factory()->create([
            'product_id' => $product->id,
            'quantity' => 3,
            'from_location_type' => 'plantel',
            'from_vendedor_id' => null,
            'to_location_type' => 'vendedor',
            'to_vendedor_id' => $vendedor->id,
            'is_mock' => false,
        ]);

        $this->assertSame(7, $product->fresh()->stock);

        StockTransfer::factory()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'from_location_type' => 'vendedor',
            'from_vendedor_id' => $vendedor->id,
            'to_location_type' => 'plantel',
            'to_vendedor_id' => null,
            'is_mock' => false,
        ]);

        $this->assertSame(8, $product->fresh()->stock);
    }

    public function test_mock_production_and_sales_are_excluded_from_calculated_stock(): void
    {
        DailyProduction::factory()->create(['chicken_eggs' => 300, 'quail_eggs' => 0, 'is_mock' => true]);
        $product = Product::factory()->create(['eggs_per_unit' => 30, 'egg_species' => 'chicken']);

        $this->assertSame(0, $product->fresh()->stock);
    }

    public function test_multiple_products_mapped_to_the_same_species_each_see_the_full_production(): void
    {
        // Cenário fora de escopo hoje (sem rateio), mas o cálculo não deve
        // quebrar: cada produto mapeado vê a produção total da espécie.
        DailyProduction::factory()->create(['chicken_eggs' => 300, 'quail_eggs' => 0, 'is_mock' => false]);

        $productA = Product::factory()->create(['eggs_per_unit' => 30, 'egg_species' => 'chicken']);
        $productB = Product::factory()->create(['eggs_per_unit' => 15, 'egg_species' => 'chicken']);

        $this->assertSame(10, $productA->fresh()->stock);
        $this->assertSame(20, $productB->fresh()->stock);
    }

    public function test_stock_location_service_does_not_write_raw_stock_column_for_egg_products(): void
    {
        DailyProduction::factory()->create(['chicken_eggs' => 300, 'quail_eggs' => 0, 'is_mock' => false]);
        $product = Product::factory()->create(['eggs_per_unit' => 30, 'egg_species' => 'chicken', 'stock' => 0]);

        app(StockLocationService::class)->adjust('plantel', null, $product, -4);

        $raw = $product->fresh()->getRawOriginal('stock');
        $this->assertSame(0, $raw);
    }
}
