<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\Vendedor;
use App\Models\VendorStock;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Cenário sintético do bug real de produção (2026-08-23): reimportação da
 * planilha triplicou vendas reais, cada cópia debitando estoque de novo.
 *
 * A trava `sales_duplicate_unique` (mesma migration da prevenção em
 * `StoreSaleRequest`) impede inserir duplicata via Eloquent/DB normalmente,
 * então o setup dropa o índice (se já existir) pra reproduzir o estado de
 * produção anterior à trava — exatamente o que `sales:deduplicate` precisa
 * reparar. Condicional porque a migration do índice é deployada em etapa
 * separada: o comando precisa funcionar tanto antes quanto depois dela.
 */
class DeduplicateSalesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $indexExists = collect(Schema::getIndexes('sales'))
            ->contains(fn (array $index) => $index['name'] === 'sales_duplicate_unique');

        if ($indexExists) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropUnique('sales_duplicate_unique');
            });
        }
    }

    public function test_dry_run_reports_duplicates_without_changing_anything(): void
    {
        ['productA' => $productA, 'productB' => $productB, 'vendedorB' => $vendedorB, 'productC' => $productC] = $this->seedTriplicatedProduction();

        $this->artisan('sales:deduplicate')->assertExitCode(0);

        $this->assertSame(7, Sale::count());
        $this->assertSame(70, $productA->fresh()->stock);
        $this->assertSame(-12, VendorStock::where('product_id', $productB->id)->where('vendedor_id', $vendedorB->id)->value('quantity'));
        $this->assertSame(45, $productC->fresh()->stock);
    }

    public function test_force_removes_duplicates_and_restores_stock(): void
    {
        ['productA' => $productA, 'productB' => $productB, 'vendedorB' => $vendedorB, 'productC' => $productC] = $this->seedTriplicatedProduction();

        $this->artisan('sales:deduplicate', ['--force' => true])->assertExitCode(0);

        // 1 venda sobrevive por grupo duplicado (A e B) + a venda C, que nunca foi duplicada.
        $this->assertSame(3, Sale::count());

        // Estoque como se cada venda tivesse sido criada uma única vez.
        $this->assertSame(90, $productA->fresh()->stock);
        $this->assertSame(-4, VendorStock::where('product_id', $productB->id)->where('vendedor_id', $vendedorB->id)->value('quantity'));
        $this->assertSame(45, $productC->fresh()->stock);
    }

    public function test_force_is_idempotent(): void
    {
        $this->seedTriplicatedProduction();

        $this->artisan('sales:deduplicate', ['--force' => true])->assertExitCode(0);
        $countAfterFirstRun = Sale::count();

        $this->artisan('sales:deduplicate', ['--force' => true])->assertExitCode(0);

        $this->assertSame($countAfterFirstRun, Sale::count());
    }

    /**
     * @return array{productA: Product, productB: Product, vendedorB: Vendedor, productC: Product}
     */
    private function seedTriplicatedProduction(): array
    {
        $productA = Product::factory()->create(['stock' => 100]);
        $vendedorA = Vendedor::factory()->create();

        // Grupo duplicado no Plantel: 3 cópias da mesma venda, cada uma debitando 10 do estoque na criação.
        Sale::factory()->count(3)->create([
            'date' => '2026-08-20',
            'product_id' => $productA->id,
            'quantity' => 10,
            'unit_price' => 15,
            'total' => 150,
            'buyer' => 'Maria',
            'seller_id' => $vendedorA->id,
            'stock_location_type' => 'plantel',
            'stock_location_vendedor_id' => null,
        ]);
        $productA->decrement('stock', 30);

        // Grupo duplicado no estoque de um Vendedor: 3 cópias, cada uma debitando 4.
        $productB = Product::factory()->create();
        $vendedorB = Vendedor::factory()->create();
        Sale::factory()->count(3)->create([
            'date' => '2026-08-21',
            'product_id' => $productB->id,
            'quantity' => 4,
            'unit_price' => 8,
            'total' => 32,
            'buyer' => 'João',
            'seller_id' => $vendedorA->id,
            'stock_location_type' => 'vendedor',
            'stock_location_vendedor_id' => $vendedorB->id,
        ]);
        VendorStock::create(['product_id' => $productB->id, 'vendedor_id' => $vendedorB->id, 'quantity' => -12]);

        // Venda legítima, não duplicada — não pode ser tocada pelo comando.
        $productC = Product::factory()->create(['stock' => 50]);
        Sale::factory()->create([
            'date' => '2026-08-22',
            'product_id' => $productC->id,
            'quantity' => 5,
            'unit_price' => 20,
            'total' => 100,
            'buyer' => 'Ana',
            'seller_id' => $vendedorA->id,
            'stock_location_type' => 'plantel',
            'stock_location_vendedor_id' => null,
        ]);
        $productC->decrement('stock', 5);

        return compact('productA', 'productB', 'vendedorB', 'productC');
    }
}
