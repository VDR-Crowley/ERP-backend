<?php

namespace Tests\Feature;

use App\Models\EggLoss;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** CRUD de EggLoss + trava contra duplicata (date+species+quantity+reason). */
class EggLossTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(), ['access']);
    }

    public function test_index_lists_egg_losses(): void
    {
        EggLoss::factory()->count(2)->create();

        $this->getJson('/api/egg-losses')->assertOk()->assertJsonCount(2);
    }

    public function test_store_creates_egg_loss(): void
    {
        $this->postJson('/api/egg-losses', [
            'date' => '2026-08-20',
            'species' => 'quail',
            'quantity' => 15,
            'reason' => 'quebrado',
        ])->assertCreated()->assertJsonFragment(['quantity' => 15, 'reason' => 'quebrado']);

        $this->assertSame(1, EggLoss::count());
    }

    public function test_store_allows_null_reason(): void
    {
        $this->postJson('/api/egg-losses', [
            'date' => '2026-08-20',
            'species' => 'quail',
            'quantity' => 15,
        ])->assertCreated();

        $this->assertSame(1, EggLoss::count());
    }

    public function test_store_rejects_quantity_below_one(): void
    {
        $this->postJson('/api/egg-losses', [
            'date' => '2026-08-20',
            'species' => 'quail',
            'quantity' => 0,
        ])->assertUnprocessable()->assertJsonValidationErrors('quantity');
    }

    public function test_show_returns_egg_loss(): void
    {
        $eggLoss = EggLoss::factory()->create();

        $this->getJson("/api/egg-losses/{$eggLoss->id}")->assertOk()->assertJsonFragment(['id' => $eggLoss->id]);
    }

    public function test_update_modifies_egg_loss(): void
    {
        $eggLoss = EggLoss::factory()->create(['quantity' => 10]);

        $this->putJson("/api/egg-losses/{$eggLoss->id}", [
            'date' => $eggLoss->date->toDateString(),
            'species' => $eggLoss->species,
            'quantity' => 20,
            'reason' => 'doação',
        ])->assertOk()->assertJsonFragment(['quantity' => 20, 'reason' => 'doação']);

        $this->assertSame(20, $eggLoss->fresh()->quantity);
    }

    public function test_destroy_deletes_egg_loss(): void
    {
        $eggLoss = EggLoss::factory()->create();

        $this->deleteJson("/api/egg-losses/{$eggLoss->id}")->assertNoContent();

        $this->assertSame(0, EggLoss::count());
    }

    public function test_store_rejects_exact_duplicate(): void
    {
        EggLoss::factory()->create([
            'date' => '2026-08-20',
            'species' => 'quail',
            'quantity' => 15,
            'reason' => 'quebrado',
        ]);

        $this->postJson('/api/egg-losses', [
            'date' => '2026-08-20',
            'species' => 'quail',
            'quantity' => 15,
            'reason' => 'quebrado',
        ])->assertUnprocessable()->assertJsonValidationErrors('date');

        $this->assertSame(1, EggLoss::count());
    }

    public function test_store_rejects_exact_duplicate_with_null_reason(): void
    {
        EggLoss::factory()->create([
            'date' => '2026-08-20',
            'species' => 'quail',
            'quantity' => 15,
            'reason' => null,
        ]);

        $this->postJson('/api/egg-losses', [
            'date' => '2026-08-20',
            'species' => 'quail',
            'quantity' => 15,
        ])->assertUnprocessable()->assertJsonValidationErrors('date');

        $this->assertSame(1, EggLoss::count());
    }

    public function test_store_allows_different_reason_same_day_species_and_quantity(): void
    {
        EggLoss::factory()->create([
            'date' => '2026-08-20',
            'species' => 'quail',
            'quantity' => 15,
            'reason' => 'quebrado',
        ]);

        $this->postJson('/api/egg-losses', [
            'date' => '2026-08-20',
            'species' => 'quail',
            'quantity' => 15,
            'reason' => 'doação',
        ])->assertCreated();

        $this->assertSame(2, EggLoss::count());
    }

    public function test_store_allows_different_quantity_same_day_species_and_reason(): void
    {
        EggLoss::factory()->create([
            'date' => '2026-08-20',
            'species' => 'quail',
            'quantity' => 15,
            'reason' => 'quebrado',
        ]);

        $this->postJson('/api/egg-losses', [
            'date' => '2026-08-20',
            'species' => 'quail',
            'quantity' => 8,
            'reason' => 'quebrado',
        ])->assertCreated();

        $this->assertSame(2, EggLoss::count());
    }

    public function test_store_allows_different_species_same_day(): void
    {
        EggLoss::factory()->create([
            'date' => '2026-08-20',
            'species' => 'quail',
            'quantity' => 15,
            'reason' => 'quebrado',
        ]);

        $this->postJson('/api/egg-losses', [
            'date' => '2026-08-20',
            'species' => 'chicken',
            'quantity' => 15,
            'reason' => 'quebrado',
        ])->assertCreated();

        $this->assertSame(2, EggLoss::count());
    }
}
