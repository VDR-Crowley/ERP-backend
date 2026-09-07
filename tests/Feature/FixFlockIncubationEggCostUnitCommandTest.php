<?php

namespace Tests\Feature;

use App\Models\FlockIncubation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cenário sintético do bug real de produção (2026-09-07): o front-end passou
 * a interpretar `egg_cost` como preço por ovo em vez de custo total do lote.
 * Lotes pré-existentes ainda guardam o valor no sentido antigo (total) e
 * precisam ser convertidos: `novo = antigo / egg_count`.
 */
class FixFlockIncubationEggCostUnitCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_reports_conversions_without_changing_anything(): void
    {
        $needsConversion = FlockIncubation::factory()->create(['egg_count' => 250, 'egg_cost' => 75.80]);

        $this->artisan('flock-incubations:fix-egg-cost-unit')->assertExitCode(0);

        $this->assertEquals(75.80, $needsConversion->fresh()->egg_cost);
    }

    public function test_force_converts_total_cost_to_per_egg_cost(): void
    {
        $needsConversion = FlockIncubation::factory()->create(['egg_count' => 250, 'egg_cost' => 75.80]);

        $this->artisan('flock-incubations:fix-egg-cost-unit', ['--force' => true])->assertExitCode(0);

        $this->assertEquals(0.30, $needsConversion->fresh()->egg_cost);
    }

    public function test_force_skips_zero_egg_count_without_dividing(): void
    {
        $zeroCount = FlockIncubation::factory()->create(['egg_count' => 0, 'egg_cost' => 50.00]);

        $this->artisan('flock-incubations:fix-egg-cost-unit', ['--force' => true])->assertExitCode(0);

        $this->assertEquals(50.00, $zeroCount->fresh()->egg_cost);
    }

    public function test_force_skips_values_that_already_look_per_egg(): void
    {
        // 0,35 já cai na faixa plausível de preço por ovo (R$0,20-R$1,00):
        // ambíguo, não dá pra saber se já foi convertido ou é coincidência.
        $ambiguous = FlockIncubation::factory()->create(['egg_count' => 200, 'egg_cost' => 0.35]);

        $this->artisan('flock-incubations:fix-egg-cost-unit', ['--force' => true])->assertExitCode(0);

        $this->assertEquals(0.35, $ambiguous->fresh()->egg_cost);
    }

    public function test_force_ignores_rows_with_null_egg_cost(): void
    {
        $noCost = FlockIncubation::factory()->create(['egg_count' => 100, 'egg_cost' => null]);

        $this->artisan('flock-incubations:fix-egg-cost-unit', ['--force' => true])->assertExitCode(0);

        $this->assertNull($noCost->fresh()->egg_cost);
    }

    public function test_force_is_idempotent(): void
    {
        $incubation = FlockIncubation::factory()->create(['egg_count' => 250, 'egg_cost' => 75.80]);

        $this->artisan('flock-incubations:fix-egg-cost-unit', ['--force' => true])->assertExitCode(0);
        $costAfterFirstRun = $incubation->fresh()->egg_cost;

        // Depois de convertido, 0,30 cai na faixa plausível de preço por ovo
        // e passa a ser pulado como ambíguo — não há segunda conversão.
        $this->artisan('flock-incubations:fix-egg-cost-unit', ['--force' => true])->assertExitCode(0);

        $this->assertEquals($costAfterFirstRun, $incubation->fresh()->egg_cost);
    }
}
