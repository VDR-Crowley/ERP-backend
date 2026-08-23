<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Models\Vendedor;
use App\Models\VendorStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Porte de `Sales.saveForm`/`confirmDelete` (sales.ts no front): baixa de estoque no local certo. */
class SaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(), ['access']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'date' => '2026-07-01',
            'quantity' => 5,
            'unit_price' => 15,
            'total' => 75,
            'payment_pending' => false,
            'buyer' => 'Comprador',
            'seller_id' => Vendedor::factory()->create()->id,
            'delivery_pending' => false,
            'delivery_date' => null,
            'stock_location_type' => 'plantel',
            'stock_location_vendedor_id' => null,
        ], $overrides);
    }

    public function test_creating_sale_debits_product_stock_at_plantel(): void
    {
        $product = Product::factory()->create(['stock' => 50]);

        $this->postJson('/api/sales', $this->payload(['product_id' => $product->id, 'quantity' => 5]))
            ->assertCreated();

        $this->assertSame(45, $product->fresh()->stock);
    }

    public function test_creating_sale_debits_vendor_stock_when_location_is_vendedor(): void
    {
        $product = Product::factory()->create(['stock' => 50]);
        $vendedor = Vendedor::factory()->create();

        $this->postJson('/api/sales', $this->payload([
            'product_id' => $product->id,
            'quantity' => 5,
            'stock_location_type' => 'vendedor',
            'stock_location_vendedor_id' => $vendedor->id,
        ]))->assertCreated();

        $this->assertSame(50, $product->fresh()->stock);
        $this->assertSame(-5, VendorStock::where('product_id', $product->id)->where('vendedor_id', $vendedor->id)->value('quantity'));
    }

    public function test_updating_sale_undoes_old_debit_before_applying_new_even_when_location_changes(): void
    {
        $product = Product::factory()->create(['stock' => 50]);
        $vendedor = Vendedor::factory()->create();

        $create = $this->postJson('/api/sales', $this->payload(['product_id' => $product->id, 'quantity' => 5]));
        $sale = Sale::find($create->json('id'));
        $this->assertSame(45, $product->fresh()->stock);

        $this->putJson("/api/sales/{$sale->id}", $this->payload([
            'product_id' => $product->id,
            'quantity' => 8,
            'stock_location_type' => 'vendedor',
            'stock_location_vendedor_id' => $vendedor->id,
        ]))->assertOk();

        // Plantel volta pro estoque original (baixa antiga desfeita, nova baixa foi pro vendedor).
        $this->assertSame(50, $product->fresh()->stock);
        $this->assertSame(-8, VendorStock::where('product_id', $product->id)->where('vendedor_id', $vendedor->id)->value('quantity'));
    }

    public function test_deleting_sale_returns_quantity_to_original_location(): void
    {
        $product = Product::factory()->create(['stock' => 50]);
        $create = $this->postJson('/api/sales', $this->payload(['product_id' => $product->id, 'quantity' => 5]));
        $sale = Sale::find($create->json('id'));
        $this->assertSame(45, $product->fresh()->stock);

        $this->deleteJson("/api/sales/{$sale->id}")->assertNoContent();

        $this->assertSame(50, $product->fresh()->stock);
    }

    public function test_store_requires_vendedor_id_when_location_type_is_vendedor(): void
    {
        $product = Product::factory()->create();

        $this->postJson('/api/sales', $this->payload(['product_id' => $product->id, 'stock_location_type' => 'vendedor', 'stock_location_vendedor_id' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('stock_location_vendedor_id');
    }

    public function test_store_rejects_vendedor_id_when_location_type_is_plantel(): void
    {
        $product = Product::factory()->create();
        $vendedor = Vendedor::factory()->create();

        $this->postJson('/api/sales', $this->payload(['product_id' => $product->id, 'stock_location_type' => 'plantel', 'stock_location_vendedor_id' => $vendedor->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('stock_location_vendedor_id');
    }
}
