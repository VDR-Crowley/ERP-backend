<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Flock;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** GET /business-line-report — porte de buildBusinessLineReport/buildProductReport (business-line-report.util.ts). */
class BusinessLineReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(), ['access']);

        Flock::factory()->create(['species' => 'Codornas', 'quantity' => 130]);
        Flock::factory()->create(['species' => 'Galinhas Embrapa 051', 'quantity' => 32]);
    }

    private function sale(Product $product, array $overrides = []): Sale
    {
        return Sale::factory()->create(array_merge(['product_id' => $product->id], $overrides));
    }

    public function test_aggregates_revenue_cost_and_profit_by_species(): void
    {
        $codorna = Product::factory()->create(['name' => '50 ovos de codorna', 'unit_price' => 15, 'eggs_per_unit' => 50]);
        $galinha = Product::factory()->create(['name' => '1 Bandeja de ovos de galinha', 'unit_price' => 20, 'eggs_per_unit' => 30]);
        $this->sale($codorna, ['total' => 150, 'quantity' => 10]);
        $this->sale($galinha, ['total' => 40, 'quantity' => 2]);
        Expense::factory()->create(['description' => 'Ração codornas', 'category' => 'Ração', 'amount' => 106]);
        Expense::factory()->create(['description' => 'Ração galinhas', 'category' => 'Ração', 'amount' => 200]);

        $response = $this->getJson('/api/business-line-report')->assertOk();

        $response->assertJsonPath('by_species.quail.revenue', 150)
            ->assertJsonPath('by_species.quail.cost', 106)
            ->assertJsonPath('by_species.chicken.revenue', 40)
            ->assertJsonPath('by_species.chicken.cost', 200)
            ->assertJsonPath('by_species.chicken_covers_costs', false);
        $this->assertCount(2, $response->json('by_product'));
    }

    public function test_excludes_sales_marked_as_isolated_events(): void
    {
        $codorna = Product::factory()->create(['name' => '50 ovos de codorna', 'unit_price' => 15, 'eggs_per_unit' => 50]);
        $excluded = $this->sale($codorna, ['total' => 500, 'quantity' => 1]);
        $this->sale($codorna, ['total' => 150, 'quantity' => 10]);

        $this->postJson("/api/sales/{$excluded->id}/exclusion", ['reason' => 'Venda promocional atípica'])->assertCreated();

        $response = $this->getJson('/api/business-line-report')->assertOk();

        $response->assertJsonPath('by_species.quail.revenue', 150);
    }

    public function test_respects_manual_expense_species_override(): void
    {
        $codorna = Product::factory()->create(['name' => '50 ovos de codorna', 'unit_price' => 15, 'eggs_per_unit' => 50]);
        $this->sale($codorna, ['total' => 150, 'quantity' => 10]);
        $expense = Expense::factory()->create(['description' => 'Saco de ração de galinha usado pras codornas', 'category' => 'Ração Galinha', 'amount' => 106]);

        $this->postJson("/api/expenses/{$expense->id}/species-override", ['species' => 'quail', 'reason' => 'Saco trocado'])->assertCreated();

        $response = $this->getJson('/api/business-line-report')->assertOk();

        $response->assertJsonPath('by_species.quail.cost', 106)
            ->assertJsonPath('by_species.chicken.cost', 0);
    }

    public function test_filters_by_date_range(): void
    {
        $codorna = Product::factory()->create(['name' => '50 ovos de codorna', 'unit_price' => 15, 'eggs_per_unit' => 50]);
        $this->sale($codorna, ['date' => '2026-06-15', 'total' => 15, 'quantity' => 1]);
        $this->sale($codorna, ['date' => '2026-07-15', 'total' => 30, 'quantity' => 2]);

        $response = $this->getJson('/api/business-line-report?start=2026-07-01&end=2026-07-31')->assertOk();

        $response->assertJsonPath('by_species.quail.revenue', 30);
        $this->assertSame([['month' => '2026-07', 'quail_revenue' => 30, 'chicken_revenue' => 0]], $response->json('monthly_series'));
    }
}
