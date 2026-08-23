<?php

namespace App\Models;

use App\Models\Concerns\HasMockFlag;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['date', 'quail_eggs', 'chicken_eggs', 'quail_packs', 'chicken_packs', 'quail_stock_value', 'chicken_stock_value', 'is_mock'])]
class EggStock extends Model
{
    use HasFactory, HasMockFlag;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'quail_eggs' => 'integer',
            'chicken_eggs' => 'integer',
            'quail_packs' => 'decimal:2',
            'chicken_packs' => 'decimal:2',
            'quail_stock_value' => 'decimal:2',
            'chicken_stock_value' => 'decimal:2',
            'is_mock' => 'boolean',
        ];
    }
}
