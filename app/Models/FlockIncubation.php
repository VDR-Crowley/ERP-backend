<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['start_date', 'species', 'egg_count', 'expected_hatch_date', 'status', 'egg_cost', 'feed_cost', 'notes'])]
class FlockIncubation extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'expected_hatch_date' => 'date',
            'egg_count' => 'integer',
            'egg_cost' => 'decimal:2',
            'feed_cost' => 'decimal:2',
        ];
    }

    public function hatchEvents(): HasMany
    {
        return $this->hasMany(HatchEvent::class);
    }
}
