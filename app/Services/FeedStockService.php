<?php

namespace App\Services;

use App\Models\FeedStock;
use Illuminate\Support\Facades\DB;

/**
 * Porte de `ControleRacao.saveReplenish`/`saveOpenBag` (controle-racao.ts no
 * front). Reposição soma sacos/kg e troca o peso de referência do saco pro da
 * remessa reposta; abertura de saco decrementa 1 saco e `lastBagWeightKg` do
 * saldo (sem alterar `last_bag_weight_kg`) e registra o log correspondente.
 */
class FeedStockService
{
    public function replenish(FeedStock $feedStock, array $data): FeedStock
    {
        $feedStock->update([
            'bags_in_stock' => $feedStock->bags_in_stock + $data['bags'],
            'kg_in_stock' => $feedStock->kg_in_stock + $data['bags'] * $data['bag_weight_kg'],
            'last_bag_weight_kg' => $data['bag_weight_kg'],
            'expiration_date' => $data['expiration_date'],
        ]);

        return $feedStock;
    }

    public function openBag(FeedStock $feedStock, array $data): FeedStock
    {
        return DB::transaction(function () use ($feedStock, $data) {
            $feedStock->update([
                'bags_in_stock' => $feedStock->bags_in_stock - 1,
                'kg_in_stock' => $feedStock->kg_in_stock - $data['weight_kg'],
            ]);

            $feedStock->openLogs()->create([
                'feed_type' => $feedStock->type,
                'date' => $data['date'],
                'weight_kg' => $data['weight_kg'],
            ]);

            return $feedStock;
        });
    }
}
