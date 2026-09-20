<?php

use App\Models\Barn;
use App\Models\DailyProduction;
use Illuminate\Database\Migrations\Migration;

/**
 * Correção pontual: produções diárias sem galpão (`barn_id` null) — vindas do
 * import da planilha antes da coluna "Galpão" — passam a apontar pro Galpão 1
 * (primeiro galpão). Baseline pedido pelo usuário: toda produção vira Galpão 1,
 * e depois ele reatribui manualmente a do Galpão 2 (iniciada em 15/09). Só toca
 * nas linhas sem galpão, então não sobrescreve nada já atribuído. No-op se não
 * houver galpão.
 */
return new class extends Migration
{
    public function up(): void
    {
        $barn = Barn::orderBy('id')->first();
        if ($barn === null) {
            return; // banco sem galpões (ex.: fresh) — nada a converter
        }

        DailyProduction::whereNull('barn_id')->update(['barn_id' => $barn->id]);
    }

    public function down(): void
    {
        // Sem reversão automática (correção de dado pontual).
    }
};
