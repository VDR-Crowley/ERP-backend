<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['feed_stock_id', 'feed_type', 'date', 'weight_kg'])]
class FeedOpenLog extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'weight_kg' => 'decimal:2',
        ];
    }

    public function feedStock(): BelongsTo
    {
        return $this->belongsTo(FeedStock::class);
    }
}
