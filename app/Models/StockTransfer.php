<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'date', 'product_id', 'quantity',
    'from_location_type', 'from_vendedor_id',
    'to_location_type', 'to_vendedor_id', 'note',
])]
class StockTransfer extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'quantity' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function fromVendedor(): BelongsTo
    {
        return $this->belongsTo(Vendedor::class, 'from_vendedor_id');
    }

    public function toVendedor(): BelongsTo
    {
        return $this->belongsTo(Vendedor::class, 'to_vendedor_id');
    }
}
