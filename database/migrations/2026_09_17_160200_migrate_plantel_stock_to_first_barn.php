<?php

use App\Models\Barn;
use App\Models\BarnStock;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Database\Migrations\Migration;

/**
 * Migra o estoque "Plantel" (global, products.stock) e as vendas antigas com
 * local 'plantel' pro primeiro galpão cadastrado. Decisão do usuário: o
 * "Plantel" genérico é substituído por galpões, e o saldo atual vai todo pro
 * Galpão 1. No-op se não houver galpão (banco novo, sem dados).
 */
return new class extends Migration
{
    public function up(): void
    {
        $barn = Barn::orderBy('id')->first();
        if ($barn === null) {
            return; // banco sem galpões (ex.: fresh) — nada a migrar
        }

        foreach (Product::all() as $product) {
            if ($product->stock != 0) {
                BarnStock::updateOrCreate(
                    ['barn_id' => $barn->id, 'product_id' => $product->id],
                    ['quantity' => $product->stock],
                );
                $product->stock = 0;
                $product->save();
            }
        }

        Sale::where('stock_location_type', 'plantel')->update([
            'stock_location_type' => 'barn',
            'stock_location_barn_id' => $barn->id,
        ]);
    }

    public function down(): void
    {
        // Sem reversão automática (migração de dado pontual).
    }
};
