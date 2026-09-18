<?php

namespace App\Models;

use App\Models\Concerns\HasMockFlag;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Saldo de estoque de um produto num galpão específico. Ver StockLocationService. */
#[Fillable(['barn_id', 'product_id', 'quantity', 'is_mock'])]
class BarnStock extends Model
{
    use HasMockFlag;

    protected $table = 'barn_stock';

    protected function casts(): array
    {
        return [
            'barn_id' => 'integer',
            'product_id' => 'integer',
            'quantity' => 'integer',
            'is_mock' => 'boolean',
        ];
    }

    public function barn(): BelongsTo
    {
        return $this->belongsTo(Barn::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
