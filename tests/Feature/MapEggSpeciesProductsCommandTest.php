<?php

namespace Tests\Feature;

use App\Models\DailyProduction;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** `products:map-egg-species`: escopo fechado nos 2 produtos aprovados, dry-run por padrão. */
class MapEggSpeciesProductsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_does_not_persist_egg_species(): void
    {
        Product::factory()->create(['name' => '1 Bandeja de ovos de galinha', 'eggs_per_unit' => 30, 'egg_species' => null]);
        Product::factory()->create(['name' => '50 ovos de codorna', 'eggs_per_unit' => 50, 'egg_species' => null]);

        $this->artisan('products:map-egg-species')->assertSuccessful();

        $this->assertNull(Product::where('name', '1 Bandeja de ovos de galinha')->value('egg_species'));
        $this->assertNull(Product::where('name', '50 ovos de codorna')->value('egg_species'));
    }

    public function test_force_persists_egg_species_on_the_two_mapped_products_only(): void
    {
        $chicken = Product::factory()->create(['name' => '1 Bandeja de ovos de galinha', 'eggs_per_unit' => 30, 'egg_species' => null]);
        $quail = Product::factory()->create(['name' => '50 ovos de codorna', 'eggs_per_unit' => 50, 'egg_species' => null]);
        $untouched = Product::factory()->create(['name' => '10 Codornas Abatida', 'eggs_per_unit' => 0, 'egg_species' => null]);

        $this->artisan('products:map-egg-species --force')->assertSuccessful();

        $this->assertSame('chicken', $chicken->fresh()->egg_species);
        $this->assertSame('quail', $quail->fresh()->egg_species);
        $this->assertNull($untouched->fresh()->egg_species);
    }

    public function test_dry_run_shows_calculated_stock_that_would_result(): void
    {
        DailyProduction::factory()->create(['chicken_eggs' => 300, 'quail_eggs' => 500, 'is_mock' => false]);
        Product::factory()->create(['name' => '1 Bandeja de ovos de galinha', 'eggs_per_unit' => 30, 'egg_species' => null]);
        Product::factory()->create(['name' => '50 ovos de codorna', 'eggs_per_unit' => 50, 'egg_species' => null]);

        $this->artisan('products:map-egg-species')
            ->assertSuccessful()
            ->expectsTable(
                ['ID', 'Produto', 'egg_species', 'Status', 'Estoque calculado (Plantel)'],
                [
                    [1, '1 Bandeja de ovos de galinha', 'chicken', 'seria aplicado', 10],
                    [2, '50 ovos de codorna', 'quail', 'seria aplicado', 10],
                ]
            );
    }

    public function test_missing_product_is_skipped_without_error(): void
    {
        Product::factory()->create(['name' => '1 Bandeja de ovos de galinha', 'eggs_per_unit' => 30, 'egg_species' => null]);
        // "50 ovos de codorna" não existe.

        $this->artisan('products:map-egg-species --force')->assertSuccessful();
    }
}
