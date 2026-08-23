<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Sinalização de dado mock/teste via coluna `is_mock` (ver migration
 * `add_is_mock_to_entity_tables`). Decisão: SEM Global Scope — queries
 * normais (`all()`, `index` das rotas) continuam trazendo mock + real
 * misturados por padrão. Filtrar é opt-in via `real()`/`mock()` — um Global
 * Scope automático esconderia dado sem o caller pedir, o que é pior do que
 * mostrar mock sem querer (dado escondido é mais fácil de não perceber que
 * dado mock sinalizado). Quem precisa só do dado real (relatórios,
 * dashboards de produção) chama `Model::real()->...` explicitamente.
 */
trait HasMockFlag
{
    public function scopeReal(Builder $query): Builder
    {
        return $query->where('is_mock', false);
    }

    public function scopeMock(Builder $query): Builder
    {
        return $query->where('is_mock', true);
    }
}
