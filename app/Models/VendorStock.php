<?php

namespace App\Models;

use App\Models\Concerns\HasMockFlag;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'vendedor_id', 'quantity', 'is_mock'])]
class VendorStock extends Model
{
    use HasFactory, HasMockFlag;

    /** Tabela no singular — o inflector padrão pluralizaria pra "vendor_stocks". */
    protected $table = 'vendor_stock';

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'is_mock' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(Vendedor::class);
    }
}
