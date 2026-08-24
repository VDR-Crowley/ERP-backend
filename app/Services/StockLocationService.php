<?php

namespace App\Services;

use App\Models\Product;
use App\Models\VendorStock;

/**
 * Ajuste de saldo de estoque por local (Plantel ou um Vendedor), porte fiel do
 * `adjustStock` duplicado em `sales.ts`/`stock-transfers.ts` no front. `delta`
 * negativo baixa, positivo devolve/repõe. Não bloqueia estoque negativo — mesmo
 * comportamento lenient do front (ver `stock-location.ts`).
 */
class StockLocationService
{
    public function adjust(string $locationType, ?int $vendedorId, Product $product, int $delta): void
    {
        if ($delta === 0) {
            return;
        }

        if ($locationType === 'plantel') {
            $product->increment('stock', $delta);

            return;
        }

        $vendorStock = VendorStock::firstOrNew([
            'product_id' => $product->id,
            'vendedor_id' => $vendedorId,
        ]);
        $vendorStock->quantity = ($vendorStock->quantity ?? 0) + $delta;
        $vendorStock->save();
    }

    /** Saldo atual do produto num local, pra validação de saldo suficiente (transferência). */
    public function quantityAt(string $locationType, ?int $vendedorId, Product $product): int
    {
        if ($locationType === 'plantel') {
            return $product->stock;
        }

        return VendorStock::query()
            ->where('product_id', $product->id)
            ->where('vendedor_id', $vendedorId)
            ->value('quantity') ?? 0;
    }
}
