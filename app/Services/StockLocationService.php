<?php

namespace App\Services;

use App\Models\BarnStock;
use App\Models\Product;
use App\Models\VendorStock;

/**
 * Ajuste de saldo de estoque por local. Três tipos:
 *  - 'barn'     -> saldo por galpão+produto (barn_stock)
 *  - 'vendedor' -> saldo por vendedor+produto (vendor_stock)
 *  - 'plantel'  -> legado: o antigo estoque global (products.stock)
 *
 * `delta` negativo baixa, positivo devolve/repõe. Não bloqueia estoque
 * negativo — mesmo comportamento lenient do front (ver `stock-location.ts`).
 */
class StockLocationService
{
    public function adjust(string $locationType, ?int $vendedorId, ?int $barnId, Product $product, int $delta): void
    {
        if ($delta === 0) {
            return;
        }

        if ($locationType === 'plantel') {
            $product->increment('stock', $delta);

            return;
        }

        if ($locationType === 'barn') {
            $barnStock = BarnStock::firstOrNew([
                'barn_id' => $barnId,
                'product_id' => $product->id,
            ]);
            $barnStock->quantity = ($barnStock->quantity ?? 0) + $delta;
            $barnStock->save();

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
    public function quantityAt(string $locationType, ?int $vendedorId, ?int $barnId, Product $product): int
    {
        if ($locationType === 'plantel') {
            return $product->stock;
        }

        if ($locationType === 'barn') {
            return BarnStock::query()
                ->where('barn_id', $barnId)
                ->where('product_id', $product->id)
                ->value('quantity') ?? 0;
        }

        return VendorStock::query()
            ->where('product_id', $product->id)
            ->where('vendedor_id', $vendedorId)
            ->value('quantity') ?? 0;
    }
}
