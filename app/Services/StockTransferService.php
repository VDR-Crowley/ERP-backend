<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockTransfer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Porte de `StockTransfers.saveForm` (stock-transfers.ts no front): move a
 * quantidade do local de origem pro de destino. Diferente do front (que é
 * lenient e nunca bloqueia estoque negativo), aqui a transferência exige
 * saldo suficiente no local de origem — decisão de endurecer a regra no
 * backend, aprovada pelo usuário.
 */
class StockTransferService
{
    public function __construct(private readonly StockLocationService $stock) {}

    public function create(array $data): StockTransfer
    {
        $data = $this->normalizeLocations($data);

        return DB::transaction(function () use ($data) {
            $product = Product::findOrFail($data['product_id']);

            $this->assertSufficientBalance(
                $data['from_location_type'],
                $data['from_vendedor_id'] ?? null,
                $data['from_location_barn_id'] ?? null,
                $product,
                $data['quantity'],
            );

            $transfer = StockTransfer::create($data);

            $this->stock->adjust($transfer->from_location_type, $transfer->from_vendedor_id, $transfer->from_location_barn_id, $product, -$transfer->quantity);
            $this->stock->adjust($transfer->to_location_type, $transfer->to_vendedor_id, $transfer->to_location_barn_id, $product, $transfer->quantity);

            return $transfer;
        });
    }

    public function update(StockTransfer $transfer, array $data): StockTransfer
    {
        $data = $this->normalizeLocations($data);

        return DB::transaction(function () use ($transfer, $data) {
            $originalProduct = Product::findOrFail($transfer->product_id);

            // Desfaz o movimento antigo antes de validar/aplicar o novo.
            $this->stock->adjust($transfer->from_location_type, $transfer->from_vendedor_id, $transfer->from_location_barn_id, $originalProduct, $transfer->quantity);
            $this->stock->adjust($transfer->to_location_type, $transfer->to_vendedor_id, $transfer->to_location_barn_id, $originalProduct, -$transfer->quantity);

            $newProduct = Product::findOrFail($data['product_id']);

            $this->assertSufficientBalance(
                $data['from_location_type'],
                $data['from_vendedor_id'] ?? null,
                $data['from_location_barn_id'] ?? null,
                $newProduct,
                $data['quantity'],
            );

            $transfer->update($data);

            $this->stock->adjust($transfer->from_location_type, $transfer->from_vendedor_id, $transfer->from_location_barn_id, $newProduct, -$transfer->quantity);
            $this->stock->adjust($transfer->to_location_type, $transfer->to_vendedor_id, $transfer->to_location_barn_id, $newProduct, $transfer->quantity);

            return $transfer;
        });
    }

    public function delete(StockTransfer $transfer): void
    {
        DB::transaction(function () use ($transfer) {
            $product = Product::findOrFail($transfer->product_id);

            $this->stock->adjust($transfer->from_location_type, $transfer->from_vendedor_id, $transfer->from_location_barn_id, $product, $transfer->quantity);
            $this->stock->adjust($transfer->to_location_type, $transfer->to_vendedor_id, $transfer->to_location_barn_id, $product, -$transfer->quantity);

            $transfer->delete();
        });
    }

    /** Zera os ids de local que não correspondem ao tipo escolhido, na origem e no destino. */
    private function normalizeLocations(array $data): array
    {
        $from = $data['from_location_type'] ?? null;
        if ($from !== 'vendedor') {
            $data['from_vendedor_id'] = null;
        }
        if ($from !== 'barn') {
            $data['from_location_barn_id'] = null;
        }

        $to = $data['to_location_type'] ?? null;
        if ($to !== 'vendedor') {
            $data['to_vendedor_id'] = null;
        }
        if ($to !== 'barn') {
            $data['to_location_barn_id'] = null;
        }

        return $data;
    }

    private function assertSufficientBalance(string $locationType, ?int $vendedorId, ?int $barnId, Product $product, int $quantity): void
    {
        $available = $this->stock->quantityAt($locationType, $vendedorId, $barnId, $product);

        if ($available < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => "Saldo insuficiente no local de origem (disponível: {$available}).",
            ]);
        }
    }
}
