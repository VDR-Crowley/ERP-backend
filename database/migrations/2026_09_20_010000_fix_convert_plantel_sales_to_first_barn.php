<?php

use App\Models\Barn;
use App\Models\Sale;
use Illuminate\Database\Migrations\Migration;

/**
 * Correção pontual: vendas com `stock_location_type = 'plantel'` (legado)
 * viram Galpão 1 (primeiro galpão cadastrado). A migração 160200 já fez isso
 * uma vez, mas o import posterior da planilha criou novas vendas com o default
 * 'plantel'. Aqui reconvertê-mos só o LABEL das vendas — não toca em estoque
 * (barn_stock/products.stock foram ajustados manualmente). No-op se não houver
 * galpão ou se não houver mais vendas 'plantel'.
 */
return new class extends Migration
{
    public function up(): void
    {
        $barn = Barn::orderBy('id')->first();
        if ($barn === null) {
            return; // banco sem galpões (ex.: fresh) — nada a converter
        }

        Sale::where('stock_location_type', 'plantel')->update([
            'stock_location_type' => 'barn',
            'stock_location_barn_id' => $barn->id,
        ]);
    }

    public function down(): void
    {
        // Sem reversão automática (correção de dado pontual).
    }
};
