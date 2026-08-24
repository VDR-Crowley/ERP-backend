<?php

namespace Tests\Feature;

use App\Models\EggStock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Estoque de ovos pode ficar negativo temporariamente (venda lançada antes da
 * produção correspondente ser registrada) — mesmo comportamento já aceito no
 * import via `import:planilha`. A validação do endpoint precisa refletir isso.
 */
class EggStockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(), ['access']);
    }

    public function test_store_accepts_negative_quail_stock(): void
    {
        $this->postJson('/api/egg-stocks', [
            'date' => '2026-07-05',
            'quail_eggs' => -250,
            'chicken_eggs' => 60,
            'quail_packs' => -5,
            'chicken_packs' => 2,
            'quail_stock_value' => -75,
            'chicken_stock_value' => 50,
        ])->assertCreated()
            ->assertJsonPath('quail_eggs', -250)
            ->assertJsonPath('quail_packs', '-5.00')
            ->assertJsonPath('quail_stock_value', '-75.00');

        $this->assertDatabaseHas('egg_stocks', [
            'date' => '2026-07-05 00:00:00',
            'quail_eggs' => -250,
        ]);
    }

    public function test_update_accepts_negative_quail_stock(): void
    {
        $eggStock = EggStock::factory()->create([
            'quail_eggs' => 10,
            'quail_packs' => 1,
            'quail_stock_value' => 15,
        ]);

        $this->putJson("/api/egg-stocks/{$eggStock->id}", [
            'date' => $eggStock->date->toDateString(),
            'quail_eggs' => -50,
            'chicken_eggs' => 60,
            'quail_packs' => -1,
            'chicken_packs' => 2,
            'quail_stock_value' => -15,
            'chicken_stock_value' => 50,
        ])->assertOk()
            ->assertJsonPath('quail_eggs', -50);

        $eggStock->refresh();
        $this->assertSame(-50, $eggStock->quail_eggs);
    }

    public function test_store_still_rejects_negative_chicken_stock(): void
    {
        $this->postJson('/api/egg-stocks', [
            'date' => '2026-07-05',
            'quail_eggs' => 10,
            'chicken_eggs' => -1,
            'quail_packs' => 1,
            'chicken_packs' => -1,
            'quail_stock_value' => 15,
            'chicken_stock_value' => -1,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['chicken_eggs', 'chicken_packs', 'chicken_stock_value']);
    }
}
