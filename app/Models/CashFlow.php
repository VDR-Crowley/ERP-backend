<?php

namespace App\Models;

use App\Models\Concerns\HasMockFlag;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['date', 'description', 'inflow', 'amount', 'is_mock'])]
class CashFlow extends Model
{
    use HasFactory, HasMockFlag;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'inflow' => 'boolean',
            'amount' => 'decimal:2',
            'is_mock' => 'boolean',
        ];
    }
}
