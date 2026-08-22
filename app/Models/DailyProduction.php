<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['date', 'quail_eggs', 'chicken_eggs'])]
class DailyProduction extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'quail_eggs' => 'integer',
            'chicken_eggs' => 'integer',
        ];
    }
}
