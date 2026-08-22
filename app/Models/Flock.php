<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['species', 'quantity', 'feed_bags_per_month', 'bag_price', 'monthly_total'])]
class Flock extends Model
{
    use HasFactory;

    /** Tabela no singular (mesmo nome do store `flock` no front) — o inflector padrão pluralizaria pra "flocks". */
    protected $table = 'flock';

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'feed_bags_per_month' => 'integer',
            'bag_price' => 'decimal:2',
            'monthly_total' => 'decimal:2',
        ];
    }
}
