<?php

namespace App\Models;

use App\Models\Concerns\HasMockFlag;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'date', 'product_id', 'quantity', 'unit_price', 'total', 'payment_pending',
    'buyer', 'seller_id', 'delivery_pending', 'delivery_date',
    'stock_location_type', 'stock_location_vendedor_id', 'is_mock', ])]
class Sale extends Model
{
    use HasFactory, HasMockFlag;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'total' => 'decimal:2',
            'payment_pending' => 'boolean',
            'delivery_pending' => 'boolean',
            'delivery_date' => 'date',
            'is_mock' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Vendedor::class, 'seller_id');
    }

    public function stockLocationVendedor(): BelongsTo
    {
        return $this->belongsTo(Vendedor::class, 'stock_location_vendedor_id');
    }

    public function exclusion(): HasOne
    {
        return $this->hasOne(SaleExclusion::class);
    }
}
