<?php

namespace Tests\Feature;

use App\Models\FlockCleaning;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Trava contra duplicata em reimportação de planilha (date+species+cleaning_type). */
class FlockCleaningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(), ['access']);
    }

    public function test_store_rejects_exact_duplicate(): void
    {
        FlockCleaning::factory()->create([
            'date' => '2026-08-20',
            'species' => 'quail',
            'cleaning_type' => 'total',
        ]);

        $this->postJson('/api/flock-cleanings', [
            'date' => '2026-08-20',
            'species' => 'quail',
            'cleaning_type' => 'total',
        ])->assertUnprocessable()->assertJsonValidationErrors('date');

        $this->assertSame(1, FlockCleaning::count());
    }

    public function test_store_allows_different_cleaning_type_on_same_day(): void
    {
        FlockCleaning::factory()->create([
            'date' => '2026-08-20',
            'species' => 'quail',
            'cleaning_type' => 'total',
        ]);

        $this->postJson('/api/flock-cleanings', [
            'date' => '2026-08-20',
            'species' => 'quail',
            'cleaning_type' => 'feeder',
        ])->assertCreated();

        $this->assertSame(2, FlockCleaning::count());
    }

    public function test_store_allows_different_species_on_same_day(): void
    {
        FlockCleaning::factory()->create([
            'date' => '2026-08-20',
            'species' => 'quail',
            'cleaning_type' => 'total',
        ]);

        $this->postJson('/api/flock-cleanings', [
            'date' => '2026-08-20',
            'species' => 'chicken',
            'cleaning_type' => 'total',
        ])->assertCreated();

        $this->assertSame(2, FlockCleaning::count());
    }
}
