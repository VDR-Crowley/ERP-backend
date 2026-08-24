<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\StockTransfer;
use App\Models\Vendedor;
use App\Models\VendorStock;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Cenário sintético do bug real de produção (2026-08-24): reimportação da aba
 * "Vendedores" recriou os 6 vendedores do zero em vez de dar upsert
 * (`vendedores.name` sem índice único), gerando 12 linhas pra 6 vendedores
 * reais. Vendas que referenciavam a cópia "nova" ficaram com `seller_id`
 * diferente da cópia "antiga" do mesmo vendedor — o que escondeu 117 vendas
 * duplicadas de `sales:deduplicate` (agrupa por `seller_id` cru).
 *
 * O índice único de `sales` é condicional pelo mesmo motivo do
 * `DeduplicateSalesCommandTest`: a migration já existe em disco mas ainda não
 * foi deployada, então o comando precisa funcionar com ou sem ela.
 */
class MergeDuplicateVendedoresCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dropIndexIfExists('sales', 'sales_duplicate_unique');
        $this->dropIndexIfExists('vendedores', 'vendedores_name_normalized_unique');
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        $exists = collect(Schema::getIndexes($table))
            ->contains(fn (array $index) => $index['name'] === $indexName);

        if ($exists) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($indexName));
        }
    }

    public function test_dry_run_reports_duplicates_without_changing_anything(): void
    {
        $this->seedDuplicatedVendedores();

        $this->artisan('vendedores:merge-duplicates')->assertExitCode(0);

        $this->assertSame(3, Vendedor::count());
        $this->assertSame(3, VendorStock::count());
    }

    public function test_force_merges_duplicates_and_repoints_every_fk(): void
    {
        [
            'canonical' => $canonical,
            'duplicate' => $duplicate,
            'untouched' => $untouched,
            'saleOnCanonical' => $saleOnCanonical,
            'saleOnDuplicate' => $saleOnDuplicate,
            'productCollision' => $productCollision,
            'productSimple' => $productSimple,
        ] = $this->seedDuplicatedVendedores();

        $this->artisan('vendedores:merge-duplicates', ['--force' => true])->assertExitCode(0);

        // Duplicata some, canônico e vendedor não-relacionado sobrevivem.
        $this->assertSame(2, Vendedor::count());
        $this->assertModelMissing($duplicate);
        $this->assertModelExists($canonical);
        $this->assertModelExists($untouched);

        // sales.seller_id repointado pro canônico nas duas vendas.
        $this->assertSame($canonical->id, $saleOnCanonical->fresh()->seller_id);
        $this->assertSame($canonical->id, $saleOnDuplicate->fresh()->seller_id);

        // vendor_stock com colisão (mesmo produto nos dois vendedores): soma.
        $this->assertSame(1, VendorStock::where('product_id', $productCollision->id)->count());
        $this->assertSame(30, VendorStock::where('product_id', $productCollision->id)->value('quantity'));

        // vendor_stock sem colisão (só a duplicata tinha saldo): repointa direto.
        $this->assertSame(1, VendorStock::where('product_id', $productSimple->id)->count());
        $this->assertSame($canonical->id, VendorStock::where('product_id', $productSimple->id)->value('vendedor_id'));
        $this->assertSame(7, VendorStock::where('product_id', $productSimple->id)->value('quantity'));

        // stock_transfers repointado (from e to).
        $this->assertSame(0, StockTransfer::where('from_vendedor_id', $duplicate->id)->count());
        $this->assertSame(0, StockTransfer::where('to_vendedor_id', $duplicate->id)->count());
        $this->assertSame(2, StockTransfer::where('from_vendedor_id', $canonical->id)->count());
        $this->assertSame(1, StockTransfer::where('to_vendedor_id', $canonical->id)->count());
    }

    public function test_force_is_idempotent(): void
    {
        $this->seedDuplicatedVendedores();

        $this->artisan('vendedores:merge-duplicates', ['--force' => true])->assertExitCode(0);
        $countAfterFirstRun = Vendedor::count();

        $this->artisan('vendedores:merge-duplicates', ['--force' => true])->assertExitCode(0);

        $this->assertSame($countAfterFirstRun, Vendedor::count());
    }

    /**
     * Merge de vendedor seguido de sales:deduplicate: reproduz o pipeline
     * completo do incidente e prova que as 2 vendas (business-idênticas,
     * só com seller_id diferente antes do merge) viram 1 depois das duas
     * limpezas, com estoque restaurado como se a venda tivesse existido uma
     * vez só.
     */
    public function test_merge_then_sales_deduplicate_removes_the_previously_hidden_duplicate(): void
    {
        [
            'canonical' => $canonical,
            'saleOnCanonical' => $saleOnCanonical,
            'saleOnDuplicate' => $saleOnDuplicate,
        ] = $this->seedDuplicatedVendedores();

        $productA = $saleOnCanonical->product;
        // Estoque no momento do setup já reflete as 2 vendas duplicadas
        // debitando 2 cada (ver seedDuplicatedVendedores): +4 pra achar o
        // valor "como se a venda tivesse sido criada uma única vez".
        $stockAsIfCreatedOnce = $productA->fresh()->stock + $saleOnCanonical->quantity;

        $this->artisan('vendedores:merge-duplicates', ['--force' => true])->assertExitCode(0);
        $this->artisan('sales:deduplicate', ['--force' => true])->assertExitCode(0);

        $survivors = Sale::where('product_id', $productA->id)
            ->where('buyer', $saleOnCanonical->buyer)
            ->get();

        $this->assertCount(1, $survivors);
        $this->assertSame($canonical->id, $survivors->first()->seller_id);

        // Uma das duas cópias debitou estoque na criação (setup); com só 1
        // venda sobrevivendo, o estoque devolvido pelo dedupe deixa como se
        // ela tivesse sido criada uma única vez.
        $this->assertSame($stockAsIfCreatedOnce, $productA->fresh()->stock);
    }

    /**
     * @return array{
     *     canonical: Vendedor, duplicate: Vendedor, untouched: Vendedor,
     *     saleOnCanonical: Sale, saleOnDuplicate: Sale,
     *     productCollision: Product, productSimple: Product,
     * }
     */
    private function seedDuplicatedVendedores(): array
    {
        // Vendedor duplicado: mesmo nome (incl. variação de espaço/caixa),
        // ids diferentes — exatamente o padrão achado em produção.
        $canonical = Vendedor::factory()->create(['name' => 'Ytallo']);
        $duplicate = Vendedor::factory()->create(['name' => '  ytallo  ']);
        $untouched = Vendedor::factory()->create(['name' => 'Karol']);

        // Mesma venda de negócio (data+produto+qtd+preço+comprador), só o
        // seller_id difere — por isso sales:deduplicate não pegava sozinho.
        $productA = Product::factory()->create(['stock' => 100]);
        $saleOnCanonical = Sale::factory()->create([
            'date' => '2026-06-20',
            'product_id' => $productA->id,
            'quantity' => 2,
            'unit_price' => 20,
            'total' => 40,
            'buyer' => 'Marcelo',
            'seller_id' => $canonical->id,
            'stock_location_type' => 'plantel',
            'stock_location_vendedor_id' => null,
        ]);
        $saleOnDuplicate = Sale::factory()->create([
            'date' => '2026-06-20',
            'product_id' => $productA->id,
            'quantity' => 2,
            'unit_price' => 20,
            'total' => 40,
            'buyer' => 'Marcelo',
            'seller_id' => $duplicate->id,
            'stock_location_type' => 'plantel',
            'stock_location_vendedor_id' => null,
        ]);
        // Cada cópia debitou 2 do estoque na criação, como no bug real.
        $productA->decrement('stock', 4);

        // vendor_stock com colisão: canônico e duplicata têm saldo pro
        // MESMO produto — merge tem que somar, não pisar um no outro
        // (unique(product_id, vendedor_id) impede update direto).
        $productCollision = Product::factory()->create();
        VendorStock::factory()->create(['product_id' => $productCollision->id, 'vendedor_id' => $canonical->id, 'quantity' => 10]);
        VendorStock::factory()->create(['product_id' => $productCollision->id, 'vendedor_id' => $duplicate->id, 'quantity' => 20]);

        // vendor_stock sem colisão: só a duplicata tem saldo pra esse
        // produto — merge repointa vendedor_id direto, sem conflito.
        $productSimple = Product::factory()->create();
        VendorStock::factory()->create(['product_id' => $productSimple->id, 'vendedor_id' => $duplicate->id, 'quantity' => 7]);

        // stock_transfers referenciando a duplicata nos dois papéis (from e to).
        StockTransfer::factory()->create(['from_vendedor_id' => $duplicate->id, 'from_location_type' => 'vendedor', 'to_vendedor_id' => null, 'to_location_type' => 'plantel']);
        StockTransfer::factory()->create(['from_vendedor_id' => $duplicate->id, 'from_location_type' => 'vendedor', 'to_vendedor_id' => null, 'to_location_type' => 'plantel']);
        StockTransfer::factory()->create(['to_vendedor_id' => $duplicate->id, 'to_location_type' => 'vendedor', 'from_vendedor_id' => null, 'from_location_type' => 'plantel']);

        return compact(
            'canonical', 'duplicate', 'untouched',
            'saleOnCanonical', 'saleOnDuplicate',
            'productCollision', 'productSimple',
        );
    }
}
