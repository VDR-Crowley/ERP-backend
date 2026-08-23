<?php

namespace App\Services;

use App\Models\FlockIncubation;
use App\Models\HatchEvent;
use Illuminate\Support\Facades\DB;

/**
 * Porte de `deriveStatusAfterHatchChange` (hatch-tracking.util.ts): fecha o
 * lote ("eclodido") automaticamente quando a soma dos `hatch_events` atinge
 * `egg_count`. Lote já "eclodido" nunca reabre sozinho — editar/remover um
 * evento pra um total menor não desfaz o fechamento (outra forma de fechar é
 * a ação manual "Concluir lote", fora daqui).
 */
class HatchEventService
{
    public function create(FlockIncubation $flockIncubation, array $data): HatchEvent
    {
        return DB::transaction(function () use ($flockIncubation, $data) {
            $event = $flockIncubation->hatchEvents()->create($data);

            $this->recalculateStatus($flockIncubation);

            return $event;
        });
    }

    public function update(FlockIncubation $flockIncubation, HatchEvent $event, array $data): HatchEvent
    {
        return DB::transaction(function () use ($flockIncubation, $event, $data) {
            $event->update($data);

            $this->recalculateStatus($flockIncubation);

            return $event->refresh();
        });
    }

    public function delete(FlockIncubation $flockIncubation, HatchEvent $event): void
    {
        DB::transaction(function () use ($flockIncubation, $event) {
            $event->delete();

            $this->recalculateStatus($flockIncubation);
        });
    }

    private function recalculateStatus(FlockIncubation $flockIncubation): void
    {
        if ($flockIncubation->status === 'eclodido') {
            return;
        }

        $totalHatched = (int) $flockIncubation->hatchEvents()->sum('count');

        if ($totalHatched >= $flockIncubation->egg_count) {
            $flockIncubation->update(['status' => 'eclodido']);
        }
    }
}
