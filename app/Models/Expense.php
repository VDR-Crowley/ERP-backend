<?php

namespace App\Models;

use App\Models\Concerns\HasMockFlag;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['date', 'description', 'category', 'quantity', 'unit_price', 'amount', 'paid', 'is_mock'])]
class Expense extends Model
{
    use HasFactory, HasMockFlag;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
            'paid' => 'boolean',
            'is_mock' => 'boolean',
        ];
    }

    public function speciesOverride(): HasOne
    {
        return $this->hasOne(ExpenseSpeciesOverride::class);
    }
}
