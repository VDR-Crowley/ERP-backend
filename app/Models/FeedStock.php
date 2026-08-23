<?php

namespace App\Models;

use App\Models\Concerns\HasMockFlag;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['type', 'bags_in_stock', 'kg_in_stock', 'last_bag_weight_kg', 'expiration_date', 'is_mock'])]
class FeedStock extends Model
{
    use HasFactory, HasMockFlag;

    protected function casts(): array
    {
        return [
            'bags_in_stock' => 'integer',
            'kg_in_stock' => 'decimal:2',
            'last_bag_weight_kg' => 'decimal:2',
            'expiration_date' => 'date',
            'is_mock' => 'boolean',
        ];
    }

    public function openLogs(): HasMany
    {
        return $this->hasMany(FeedOpenLog::class);
    }
}
