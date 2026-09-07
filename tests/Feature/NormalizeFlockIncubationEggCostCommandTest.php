<?php

namespace Tests\Feature;

use App\Models\FlockIncubation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cenário sintético do bug real de produção (2026-08-30, front commit
 * 4bd8e24): lote de 250 ovos com egg_cost=75.80 (total antigo do lote)
 * exibindo "Investimento até 45 dias: R$18.950,00" (250 × 75.80) em vez do
 * ~R$225-300 esperado. Ver comentário de
 * `NormalizeFlockIncubationEggCost::handle()` pra heurística de conversão.
 */
class NormalizeFlockIncubationEggCostCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_reports_conversion_without_changing_anything(): void
    {
        $lote = FlockIncubation::factory()->create(['egg_count' => 250, 'egg_cost' => 75.80]);
        $jaUnitario = FlockIncubation::factory()->create(['egg_count' => 300, 'egg_cost' => 0.30]);

        $this->artisan('flock-incubations:normalize-egg-cost')->assertExitCode(0);

        $this->assertSame('75.80', $lote->fresh()->egg_cost);
        $this->assertSame('0.30', $jaUnitario->fresh()->egg_cost);
    }

    public function test_force_converts_total_to_per_egg_price(): void
    {
        $lote = FlockIncubation::factory()->create(['egg_count' => 250, 'egg_cost' => 75.80]);

        $this->artisan('flock-incubations:normalize-egg-cost', ['--force' => true])->assertExitCode(0);

        $this->assertSame('0.30', $lote->fresh()->egg_cost);
    }

    public function test_force_does_not_touch_rows_already_at_per_egg_price(): void
    {
        $jaUnitario = FlockIncubation::factory()->create(['egg_count' => 300, 'egg_cost' => 0.30]);

        $this->artisan('flock-incubations:normalize-egg-cost', ['--force' => true])->assertExitCode(0);

        $this->assertSame('0.30', $jaUnitario->fresh()->egg_cost);
    }

    public function test_force_skips_rows_with_zero_egg_count(): void
    {
        $semQtd = FlockIncubation::factory()->create(['egg_count' => 0, 'egg_cost' => 75.80]);

        $this->artisan('flock-incubations:normalize-egg-cost', ['--force' => true])->assertExitCode(0);

        $this->assertSame('75.80', $semQtd->fresh()->egg_cost);
    }

    public function test_force_is_idempotent(): void
    {
        FlockIncubation::factory()->create(['egg_count' => 250, 'egg_cost' => 75.80]);

        $this->artisan('flock-incubations:normalize-egg-cost', ['--force' => true])->assertExitCode(0);
        $firstRunValues = FlockIncubation::query()->pluck('egg_cost')->all();

        $this->artisan('flock-incubations:normalize-egg-cost', ['--force' => true])->assertExitCode(0);
        $secondRunValues = FlockIncubation::query()->pluck('egg_cost')->all();

        $this->assertSame($firstRunValues, $secondRunValues);
    }
}
