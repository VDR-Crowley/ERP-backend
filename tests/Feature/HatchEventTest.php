<?php

namespace Tests\Feature;

use App\Models\FlockIncubation;
use App\Models\HatchEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Porte de `deriveStatusAfterHatchChange` (hatch-tracking.util.ts no front). */
class HatchEventTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(), ['access']);
    }

    public function test_keeps_incubando_while_total_hatched_is_below_egg_count(): void
    {
        $lote = FlockIncubation::factory()->create(['egg_count' => 150, 'status' => 'incubando']);

        $this->postJson("/api/flock-incubations/{$lote->id}/hatch-events", [
            'date' => '2026-07-26', 'count' => 120,
        ])->assertCreated();

        $this->assertSame('incubando', $lote->fresh()->status);
    }

    public function test_closes_lote_automatically_when_sum_of_events_reaches_egg_count(): void
    {
        $lote = FlockIncubation::factory()->create(['egg_count' => 150, 'status' => 'incubando']);
        HatchEvent::factory()->create(['flock_incubation_id' => $lote->id, 'count' => 120]);

        $this->postJson("/api/flock-incubations/{$lote->id}/hatch-events", [
            'date' => '2026-07-27', 'count' => 30,
        ])->assertCreated();

        $this->assertSame('eclodido', $lote->fresh()->status);
    }

    public function test_closes_lote_when_sum_surpasses_egg_count(): void
    {
        $lote = FlockIncubation::factory()->create(['egg_count' => 150, 'status' => 'incubando']);

        $this->postJson("/api/flock-incubations/{$lote->id}/hatch-events", [
            'date' => '2026-07-27', 'count' => 160,
        ])->assertCreated();

        $this->assertSame('eclodido', $lote->fresh()->status);
    }

    public function test_editing_history_of_a_closed_lote_never_reopens_it_automatically(): void
    {
        $lote = FlockIncubation::factory()->create(['egg_count' => 150, 'status' => 'eclodido']);
        $event = HatchEvent::factory()->create(['flock_incubation_id' => $lote->id, 'count' => 150]);

        $this->putJson("/api/flock-incubations/{$lote->id}/hatch-events/{$event->id}", [
            'date' => '2026-07-27', 'count' => 10,
        ])->assertOk();

        $this->assertSame('eclodido', $lote->fresh()->status);
    }

    public function test_deleting_event_recalculates_status_back_to_incubando(): void
    {
        $lote = FlockIncubation::factory()->create(['egg_count' => 150, 'status' => 'incubando']);
        $eventA = HatchEvent::factory()->create(['flock_incubation_id' => $lote->id, 'count' => 120]);
        $eventB = HatchEvent::factory()->create(['flock_incubation_id' => $lote->id, 'count' => 30]);

        // 120 + 30 = 150 ainda não fechou (status só recalcula em novo storeHatchEvent, não retroativamente
        // pra registros seedados direto); força o fechamento via update de um dos eventos.
        $this->putJson("/api/flock-incubations/{$lote->id}/hatch-events/{$eventB->id}", [
            'date' => '2026-07-27', 'count' => 30,
        ])->assertOk();
        $this->assertSame('eclodido', $lote->fresh()->status);

        // Remover o evento B derruba o total pra 120 (< 150) — mas o lote já fechado não reabre sozinho.
        $this->deleteJson("/api/flock-incubations/{$lote->id}/hatch-events/{$eventB->id}")->assertNoContent();
        $this->assertSame('eclodido', $lote->fresh()->status);
    }

    public function test_hatch_event_from_another_lote_returns_404(): void
    {
        $loteA = FlockIncubation::factory()->create();
        $loteB = FlockIncubation::factory()->create();
        $event = HatchEvent::factory()->create(['flock_incubation_id' => $loteB->id]);

        $this->deleteJson("/api/flock-incubations/{$loteA->id}/hatch-events/{$event->id}")->assertNotFound();
    }
}
