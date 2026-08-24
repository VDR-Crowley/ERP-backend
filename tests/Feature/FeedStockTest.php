<?php

namespace Tests\Feature;

use App\Models\FeedStock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Porte de `ControleRacao.saveReplenish`/`saveOpenBag` (controle-racao.ts no front). */
class FeedStockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(), ['access']);
    }

    public function test_replenish_adds_bags_and_kg_and_updates_reference_bag_weight(): void
    {
        $feedStock = FeedStock::factory()->create([
            'bags_in_stock' => 5,
            'kg_in_stock' => 200,
            'last_bag_weight_kg' => 40,
        ]);

        $this->postJson("/api/feed-stocks/{$feedStock->id}/replenish", [
            'bags' => 3,
            'bag_weight_kg' => 20,
            'expiration_date' => '2027-01-01',
        ])->assertOk()
            ->assertJsonPath('bags_in_stock', 8)
            ->assertJsonPath('last_bag_weight_kg', '20.00');

        $feedStock->refresh();
        $this->assertSame(8, $feedStock->bags_in_stock);
        $this->assertEqualsWithDelta(260.0, (float) $feedStock->kg_in_stock, 1e-8);
        $this->assertEqualsWithDelta(20.0, (float) $feedStock->last_bag_weight_kg, 1e-8);
        $this->assertSame('2027-01-01', $feedStock->expiration_date->toDateString());
    }

    public function test_open_bag_decrements_stock_and_creates_log(): void
    {
        $feedStock = FeedStock::factory()->create([
            'type' => 'Ração Codorna',
            'bags_in_stock' => 5,
            'kg_in_stock' => 200,
            'last_bag_weight_kg' => 40,
        ]);

        $this->postJson("/api/feed-stocks/{$feedStock->id}/open-bag", [
            'date' => '2026-07-27',
            'weight_kg' => 40,
        ])->assertOk()
            ->assertJsonPath('bags_in_stock', 4);

        $feedStock->refresh();
        $this->assertSame(4, $feedStock->bags_in_stock);
        $this->assertEqualsWithDelta(160.0, (float) $feedStock->kg_in_stock, 1e-8);
        // last_bag_weight_kg do saldo não muda ao abrir saco (só na reposição).
        $this->assertEqualsWithDelta(40.0, (float) $feedStock->last_bag_weight_kg, 1e-8);

        $this->assertDatabaseHas('feed_open_logs', [
            'feed_stock_id' => $feedStock->id,
            'feed_type' => 'Ração Codorna',
            'date' => '2026-07-27 00:00:00',
        ]);
    }

    public function test_open_bag_requires_positive_weight(): void
    {
        $feedStock = FeedStock::factory()->create();

        $this->postJson("/api/feed-stocks/{$feedStock->id}/open-bag", [
            'date' => '2026-07-27',
            'weight_kg' => 0,
        ])->assertUnprocessable()->assertJsonValidationErrors('weight_kg');
    }

    public function test_open_bag_rejects_exact_duplicate(): void
    {
        $feedStock = FeedStock::factory()->create([
            'type' => 'Codorna Postura',
            'bags_in_stock' => 5,
            'kg_in_stock' => 200,
        ]);

        $this->postJson("/api/feed-stocks/{$feedStock->id}/open-bag", [
            'date' => '2026-08-20',
            'weight_kg' => 40,
        ])->assertOk();

        $this->postJson("/api/feed-stocks/{$feedStock->id}/open-bag", [
            'date' => '2026-08-20',
            'weight_kg' => 40,
        ])->assertUnprocessable()->assertJsonValidationErrors('date');

        $this->assertSame(1, $feedStock->openLogs()->count());
        $this->assertSame(4, $feedStock->fresh()->bags_in_stock);
    }

    public function test_open_bag_allows_same_type_and_weight_on_different_days(): void
    {
        $feedStock = FeedStock::factory()->create(['type' => 'Codorna Postura']);

        $this->postJson("/api/feed-stocks/{$feedStock->id}/open-bag", [
            'date' => '2026-08-20',
            'weight_kg' => 40,
        ])->assertOk();

        $this->postJson("/api/feed-stocks/{$feedStock->id}/open-bag", [
            'date' => '2026-08-21',
            'weight_kg' => 40,
        ])->assertOk();

        $this->assertSame(2, $feedStock->openLogs()->count());
    }
}
