<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;

/**
 * Porte de `Sales.saveForm`/`confirmDelete` (sales.ts no front): toda venda
 * baixa o estoque do produto no local certo (`stock_location_type`/
 * `stock_location_vendedor_id`) ao ser criada, desfaz a baixa antiga e aplica
 * a nova ao editar (mesmo se produto/local/quantidade mudaram), e devolve a
 * quantidade ao excluir.
 */
class SaleService
{
    public function __construct(private readonly StockLocationService $stock) {}

    public function create(array $data): Sale
    {
        $data = $this->normalizeLocation($data);

        return DB::transaction(function () use ($data) {
            $sale = Sale::create($data);

            $this->stock->adjust(
                $sale->stock_location_type,
                $sale->stock_location_vendedor_id,
                Product::findOrFail($sale->product_id),
                -$sale->quantity,
            );

            return $sale;
        });
    }

    public function update(Sale $sale, array $data): Sale
    {
        $data = $this->normalizeLocation($data);

        return DB::transaction(function () use ($sale, $data) {
            $originalLocationType = $sale->stock_location_type;
            $originalLocationVendedorId = $sale->stock_location_vendedor_id;
            $originalProduct = Product::findOrFail($sale->product_id);
            $originalQuantity = $sale->quantity;

            // Desfaz a baixa antiga antes de aplicar a nova.
            $this->stock->adjust($originalLocationType, $originalLocationVendedorId, $originalProduct, $originalQuantity);

            $sale->update($data);

            $this->stock->adjust(
                $sale->stock_location_type,
                $sale->stock_location_vendedor_id,
                Product::findOrFail($sale->product_id),
                -$sale->quantity,
            );

            return $sale;
        });
    }

    public function delete(Sale $sale): void
    {
        DB::transaction(function () use ($sale) {
            $this->stock->adjust(
                $sale->stock_location_type,
                $sale->stock_location_vendedor_id,
                Product::findOrFail($sale->product_id),
                $sale->quantity,
            );

            $sale->delete();
        });
    }

    /** Garante `stock_location_vendedor_id` nulo quando o local é o Plantel, mesmo que o payload não mande a chave. */
    private function normalizeLocation(array $data): array
    {
        if (($data['stock_location_type'] ?? null) === 'plantel') {
            $data['stock_location_vendedor_id'] = null;
        }

        return $data;
    }
}
