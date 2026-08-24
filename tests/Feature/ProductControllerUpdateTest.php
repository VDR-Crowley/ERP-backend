<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductControllerUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(), ['access']);
    }

    /**
     * Regressão: a tela antiga de "editar produto" não conhece `egg_species`
     * (feature adicionada depois) e manda PUT sem esse campo — payload real
     * capturado via DevTools ao editar "1 Bandeja de ovos de galinha" (id=1):
     *
     * PUT /api/products/1
     * {"name":"...","unit":"...","unit_price":25,"stock":2,"eggs_per_unit":30}
     *
     * `egg_species` ausente no payload NUNCA pode apagar o valor já salvo —
     * `UpdateProductRequest::rules()` usa `nullable` (sem `sometimes`), e
     * `$request->validated()` já omite chaves ausentes do payload, então
     * `Product::update()` não toca `egg_species` quando ele não vem na
     * requisição. Este teste trava esse comportamento.
     */
    public function test_update_without_egg_species_field_does_not_erase_existing_value(): void
    {
        $product = Product::factory()->create([
            'name' => '1 Bandeja de ovos de galinha',
            'unit' => 'Bandeja (30 ovos)',
            'unit_price' => 25,
            'stock' => 2,
            'eggs_per_unit' => 30,
            'egg_species' => 'chicken',
        ]);

        $this->putJson("/api/products/{$product->id}", [
            'name' => '1 Bandeja de ovos de galinha',
            'unit' => 'Bandeja (30 ovos)',
            'unit_price' => 25,
            'stock' => 2,
            'eggs_per_unit' => 30,
        ])->assertOk();

        $this->assertSame('chicken', $product->fresh()->getRawOriginal('egg_species'));
    }
}
