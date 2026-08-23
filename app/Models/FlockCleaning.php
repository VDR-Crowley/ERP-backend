<?php

namespace App\Models;

use App\Models\Concerns\HasMockFlag;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['date', 'species', 'cleaning_type', 'notes', 'is_mock'])]
class FlockCleaning extends Model
{
    use HasFactory, HasMockFlag;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_mock' => 'boolean',
        ];
    }
}
