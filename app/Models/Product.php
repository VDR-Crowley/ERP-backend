<?php

namespace App\Models;

use App\Models\Concerns\HasMockFlag;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'unit', 'unit_price', 'stock', 'eggs_per_unit', 'egg_species', 'is_mock'])]
class Product extends Model
{
    use HasFactory, HasMockFlag;

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'eggs_per_unit' => 'integer',
            'is_mock' => 'boolean',
        ];
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function vendorStock(): HasMany
    {
        return $this->hasMany(VendorStock::class);
    }

    public function stockTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class);
    }

    /**
     * Estoque do Plantel. Dois comportamentos, decididos por `egg_species`:
     *
     * - `egg_species` nulo (maioria dos produtos, ex.: "10 Codornas Abatida"):
     *   coluna `stock` crua, mantida por `StockLocationService` a cada
     *   venda/transferência — comportamento de sempre, intocado.
     *
     * - `egg_species` setado (só os 2 produtos de ovo unitário mapeados, ver
     *   migration `add_egg_species_to_products_table`): CALCULADO em tempo
     *   real a cada leitura, nunca persistido. Decisão consciente (pedida
     *   pelo usuário) de calcular em vez de manter contador incrementado via
     *   observer em `DailyProduction::created`: um contador exigiria fazer
     *   `floor(ovos_do_evento / eggs_per_unit)` a cada produção isolada, que
     *   diverge do `floor` da soma acumulada (ex.: 25+25 ovos / 30 = 1
     *   unidade real, mas floor(25/30)+floor(25/30) = 0) — mesma classe de
     *   bug de drift/duplicação já resolvida em Vendas
     *   (`sales_duplicate_unique`). Calculado sempre bate com a soma bruta
     *   das tabelas de origem, sem estado próprio pra dessincronizar.
     *   `StockLocationService` pula a escrita na coluna crua pra esses
     *   produtos (ver guarda lá) — ela fica sem uso, não é lida aqui.
     */
    protected function stock(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value) => $this->egg_species === null
                ? (int) $value
                : $this->calculatePlantelEggStock(),
        );
    }

    public function isEggProduct(): bool
    {
        return $this->egg_species !== null;
    }

    private function calculatePlantelEggStock(): int
    {
        $eggColumn = match ($this->egg_species) {
            'quail' => 'quail_eggs',
            'chicken' => 'chicken_eggs',
        };

        $totalEggsProduced = (int) DailyProduction::query()->real()->sum($eggColumn);
        $unitsProduced = $this->eggs_per_unit > 0
            ? intdiv($totalEggsProduced, $this->eggs_per_unit)
            : 0;

        $soldAtPlantel = (int) Sale::query()->real()
            ->where('product_id', $this->id)
            ->where('stock_location_type', 'plantel')
            ->sum('quantity');

        $transferredOutOfPlantel = (int) StockTransfer::query()->real()
            ->where('product_id', $this->id)
            ->where('from_location_type', 'plantel')
            ->sum('quantity');

        $transferredIntoPlantel = (int) StockTransfer::query()->real()
            ->where('product_id', $this->id)
            ->where('to_location_type', 'plantel')
            ->sum('quantity');

        return $unitsProduced - $soldAtPlantel - $transferredOutOfPlantel + $transferredIntoPlantel;
    }
}
