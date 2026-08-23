<?php

namespace App\Models;

use App\Models\Concerns\HasMockFlag;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'unit', 'unit_price', 'stock', 'eggs_per_unit', 'is_mock'])]
class Product extends Model
{
    use HasFactory, HasMockFlag;

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'stock' => 'integer',
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
}
