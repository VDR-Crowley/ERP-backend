<?php

namespace Tests\Unit\Mcp;

use App\Mcp\ErpDataReader;
use App\Models\CashFlow;
use App\Models\Expense;
use App\Models\ExpenseSpeciesOverride;
use App\Models\FlockIncubation;
use App\Models\HatchEvent;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleExclusion;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErpDataReaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_products_returns_all_records_as_arrays(): void
    {
        Product::factory()->count(2)->create();

        $result = $this->reader()->read('list_products', [], []);

        $this->assertCount(2, $result);
        $this->assertArrayHasKey('name', $result[0]);
    }

    public function test_get_product_finds_by_path_param(): void
    {
        $product = Product::factory()->create();

        $result = $this->reader()->read('get_product', ['product' => $product->id], []);

        $this->assertSame($product->id, $result['id']);
    }

    public function test_get_product_throws_not_found_for_missing_id(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->reader()->read('get_product', ['product' => 999999], []);
    }

    public function test_list_users_never_leaks_the_password_hash(): void
    {
        User::factory()->create();

        $result = $this->reader()->read('list_users', [], []);

        $this->assertSame(['id', 'name', 'email', 'role', 'is_active', 'created_at'], array_keys($result[0]));
    }

    public function test_get_current_user_is_not_supported_without_a_user_identity(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/get_current_user/');

        $this->reader()->read('get_current_user', [], []);
    }

    public function test_list_sales_eager_loads_exclusion(): void
    {
        $sale = Sale::factory()->create();
        SaleExclusion::factory()->create(['sale_id' => $sale->id]);

        $result = $this->reader()->read('list_sales', [], []);

        $this->assertNotNull($result[0]['exclusion']);
    }

    public function test_get_sale_eager_loads_exclusion(): void
    {
        $sale = Sale::factory()->create();

        $result = $this->reader()->read('get_sale', ['sale' => $sale->id], []);

        $this->assertArrayHasKey('exclusion', $result);
        $this->assertNull($result['exclusion']);
    }

    public function test_list_expenses_eager_loads_species_override(): void
    {
        $expense = Expense::factory()->create();
        ExpenseSpeciesOverride::factory()->create(['expense_id' => $expense->id]);

        $result = $this->reader()->read('list_expenses', [], []);

        $this->assertNotNull($result[0]['species_override']);
    }

    public function test_list_flock_incubations_eager_loads_hatch_events(): void
    {
        $incubation = FlockIncubation::factory()->create();
        HatchEvent::factory()->create(['flock_incubation_id' => $incubation->id]);

        $result = $this->reader()->read('list_flock_incubations', [], []);

        $this->assertCount(1, $result[0]['hatch_events']);
    }

    public function test_list_hatch_events_scoped_to_one_incubation(): void
    {
        $incubation = FlockIncubation::factory()->create();
        HatchEvent::factory()->count(2)->create(['flock_incubation_id' => $incubation->id]);
        $other = FlockIncubation::factory()->create();
        HatchEvent::factory()->create(['flock_incubation_id' => $other->id]);

        $result = $this->reader()->read('list_hatch_events', ['flock_incubation' => $incubation->id], []);

        $this->assertCount(2, $result);
    }

    public function test_business_line_report_builds_from_real_data(): void
    {
        Sale::factory()->create(['date' => '2026-01-15']);

        $result = $this->reader()->read('get_business_line_report', [], ['start' => null, 'end' => null]);

        $this->assertArrayHasKey('by_species', $result);
        $this->assertArrayHasKey('by_product', $result);
        $this->assertArrayHasKey('monthly_series', $result);
    }

    public function test_business_line_report_rejects_end_before_start(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/end.*start/');

        $this->reader()->read('get_business_line_report', [], ['start' => '2026-02-01', 'end' => '2026-01-01']);
    }

    public function test_cash_flow_reads_go_straight_to_eloquent(): void
    {
        $cashFlow = CashFlow::create(['date' => '2026-01-01', 'description' => 'Teste', 'inflow' => true, 'amount' => 10]);

        $result = $this->reader()->read('get_cash_flow', ['cash_flow' => $cashFlow->id], []);

        $this->assertSame($cashFlow->id, $result['id']);
    }

    public function test_unknown_tool_throws_logic_exception(): void
    {
        $this->expectException(\LogicException::class);

        $this->reader()->read('not_a_real_tool', [], []);
    }

    private function reader(): ErpDataReader
    {
        return app(ErpDataReader::class);
    }
}
