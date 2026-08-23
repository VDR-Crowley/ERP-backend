<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Vendedor;
use App\Models\VendorStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Porte de `StockTransfers.saveForm` (stock-transfers.ts no front) + validação de saldo (regra nova do backend). */
class StockTransferTest extends TestCase
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
            'from_location_type' => 'plantel',
            'from_vendedor_id' => null,
            'to_location_type' => 'plantel',
            'to_vendedor_id' => null,
            'note' => null,
        ], $overrides);
    }

    public function test_moves_quantity_from_plantel_to_vendedor(): void
    {
        $product = Product::factory()->create(['stock' => 50]);
        $vendedor = Vendedor::factory()->create();

        $this->postJson('/api/stock-transfers', $this->payload([
            'product_id' => $product->id,
            'quantity' => 10,
            'to_location_type' => 'vendedor',
            'to_vendedor_id' => $vendedor->id,
        ]))->assertCreated();

        $this->assertSame(40, $product->fresh()->stock);
        $this->assertSame(10, VendorStock::where('product_id', $product->id)->where('vendedor_id', $vendedor->id)->value('quantity'));
    }

    public function test_rejects_transfer_when_source_balance_is_insufficient(): void
    {
        $product = Product::factory()->create(['stock' => 3]);

        $this->postJson('/api/stock-transfers', $this->payload([
            'product_id' => $product->id,
            'quantity' => 10,
            'to_location_type' => 'vendedor',
            'to_vendedor_id' => Vendedor::factory()->create()->id,
        ]))->assertUnprocessable()->assertJsonValidationErrors('quantity');

        $this->assertSame(3, $product->fresh()->stock);
    }

    public function test_rejects_transfer_when_source_and_destination_are_the_same_location(): void
    {
        $product = Product::factory()->create(['stock' => 50]);

        $this->postJson('/api/stock-transfers', $this->payload([
            'product_id' => $product->id,
            'quantity' => 5,
            'from_location_type' => 'plantel',
            'to_location_type' => 'plantel',
        ]))->assertUnprocessable()->assertJsonValidationErrors('to_location_type');
    }

    public function test_rejects_transfer_when_source_and_destination_are_the_same_vendedor(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $vendedor = Vendedor::factory()->create();
        VendorStock::factory()->create(['product_id' => $product->id, 'vendedor_id' => $vendedor->id, 'quantity' => 20]);

        $this->postJson('/api/stock-transfers', $this->payload([
            'product_id' => $product->id,
            'quantity' => 5,
            'from_location_type' => 'vendedor',
            'from_vendedor_id' => $vendedor->id,
            'to_location_type' => 'vendedor',
            'to_vendedor_id' => $vendedor->id,
        ]))->assertUnprocessable()->assertJsonValidationErrors('to_location_type');
    }

    public function test_updating_transfer_undoes_old_movement_before_applying_new(): void
    {
        $product = Product::factory()->create(['stock' => 50]);
        $vendedorA = Vendedor::factory()->create();
        $vendedorB = Vendedor::factory()->create();

        $create = $this->postJson('/api/stock-transfers', $this->payload([
            'product_id' => $product->id,
            'quantity' => 10,
            'to_location_type' => 'vendedor',
            'to_vendedor_id' => $vendedorA->id,
        ]));
        $transfer = StockTransfer::find($create->json('id'));
        $this->assertSame(40, $product->fresh()->stock);

        $this->putJson("/api/stock-transfers/{$transfer->id}", $this->payload([
            'product_id' => $product->id,
            'quantity' => 15,
            'to_location_type' => 'vendedor',
            'to_vendedor_id' => $vendedorB->id,
        ]))->assertOk();

        $this->assertSame(35, $product->fresh()->stock);
        $this->assertSame(0, (int) (VendorStock::where('product_id', $product->id)->where('vendedor_id', $vendedorA->id)->value('quantity') ?? 0));
        $this->assertSame(15, VendorStock::where('product_id', $product->id)->where('vendedor_id', $vendedorB->id)->value('quantity'));
    }

    public function test_deleting_transfer_undoes_movement(): void
    {
        $product = Product::factory()->create(['stock' => 50]);
        $vendedor = Vendedor::factory()->create();

        $create = $this->postJson('/api/stock-transfers', $this->payload([
            'product_id' => $product->id,
            'quantity' => 10,
            'to_location_type' => 'vendedor',
            'to_vendedor_id' => $vendedor->id,
        ]));
        $transfer = StockTransfer::find($create->json('id'));

        $this->deleteJson("/api/stock-transfers/{$transfer->id}")->assertNoContent();

        $this->assertSame(50, $product->fresh()->stock);
        $this->assertSame(0, (int) (VendorStock::where('product_id', $product->id)->where('vendedor_id', $vendedor->id)->value('quantity') ?? 0));
    }

    public function test_store_requires_vendedor_id_when_location_type_is_vendedor(): void
    {
        $product = Product::factory()->create();

        $this->postJson('/api/stock-transfers', $this->payload([
            'product_id' => $product->id,
            'from_location_type' => 'vendedor',
            'from_vendedor_id' => null,
        ]))->assertUnprocessable()->assertJsonValidationErrors('from_vendedor_id');
    }
}
