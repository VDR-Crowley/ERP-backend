<?php

namespace App\Models;

use App\Models\Concerns\HasMockFlag;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Galpão (plantel/local físico): abriga N lotes de aves (ver Flock). Cadastro
 * do que existe no local + dados próprios (localização, início, observação).
 */
#[Fillable(['name', 'location', 'start_date', 'notes', 'is_mock'])]
class Barn extends Model
{
    use HasFactory, HasMockFlag;

    /** Tabela no singular (mesmo padrão de `flock`) — o store no front é `barns`. */
    protected $table = 'barn';

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'is_mock' => 'boolean',
        ];
    }
}
