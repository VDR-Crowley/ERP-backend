<?php

namespace App\Models;

use App\Models\Concerns\HasMockFlag;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['feed_stock_id', 'feed_type', 'date', 'weight_kg', 'is_mock'])]
class FeedOpenLog extends Model
{
    use HasFactory, HasMockFlag;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'weight_kg' => 'decimal:2',
            'is_mock' => 'boolean',
        ];
    }

    public function feedStock(): BelongsTo
    {
        return $this->belongsTo(FeedStock::class);
    }
}
