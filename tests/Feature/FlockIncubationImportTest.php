<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Regressão pro fluxo de import do front (`import.ts` -> `flock-incubations.adapter.ts`)
 * pra aba "Novo Plantel", com as 4 linhas reais de uma planilha de usuário que reportou
 * 500. Reproduzido byte a byte: os payloads abaixo vieram de rodar o parser real do front
 * (`parseFlockIncubation` + `topLevelToApi`/`hatchEventToApi`) contra o arquivo original via
 * esbuild, não digitados à mão — ver histórico de debug. A suspeita original de bug no
 * backend não se confirmou (as 4 linhas sempre voltaram 201 aqui); a causa real do 500 em
 * produção é operacional — migrations de `flock_incubations`/`hatch_events` (2026-08-22)
 * provavelmente não rodaram no Pre-Deploy Command do Railway durante a sequência de deploys
 * quebrados corrigidos nos commits mais recentes (extensões PHP, constraint de versão,
 * Dockerfile). Este teste fica como rede de segurança pra essa combinação real de dados.
 */
class FlockIncubationImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(), ['access']);
    }

    public function test_imports_the_four_rows_from_the_reported_spreadsheet(): void
    {
        $payloads = [
            [
                'top' => [
                    'start_date' => '2026-07-15',
                    'species' => 'quail',
                    'egg_count' => 250,
                    'expected_hatch_date' => '2026-08-02',
                    'status' => 'eclodido',
                    'egg_cost' => 75,
                    'feed_cost' => 200,
                    'notes' => 'Gato matou 60, sobraram 90',
                ],
                'hatch_events' => [],
            ],
            [
                'top' => [
                    'start_date' => '2026-08-08',
                    'species' => 'quail',
                    'egg_count' => 258,
                    'expected_hatch_date' => '2026-08-26',
                    'status' => 'incubando',
                    'egg_cost' => 75,
                    'feed_cost' => 150,
                    'notes' => null,
                ],
                'hatch_events' => [],
            ],
            [
                'top' => [
                    'start_date' => '2026-04-17',
                    'species' => 'quail',
                    'egg_count' => 250,
                    'expected_hatch_date' => '2026-05-05',
                    'status' => 'eclodido',
                    'egg_cost' => 75,
                    'feed_cost' => 200,
                    'notes' => 'nasceu 120 femeas',
                ],
                'hatch_events' => [
                    ['date' => '2026-05-04', 'count' => 180, 'notes' => null],
                ],
            ],
            [
                'top' => [
                    'start_date' => '2026-05-16',
                    'species' => 'quail',
                    'egg_count' => 100,
                    'expected_hatch_date' => '2026-06-03',
                    'status' => 'eclodido',
                    'egg_cost' => 30,
                    'feed_cost' => 100,
                    'notes' => 'nasceu 35 femeas',
                ],
                'hatch_events' => [
                    ['date' => '2026-07-16', 'count' => 80, 'notes' => null],
                ],
            ],
        ];

        foreach ($payloads as $i => $spec) {
            $response = $this->postJson('/api/flock-incubations', $spec['top']);
            $response->assertCreated();
            $id = $response->json('id');

            foreach ($spec['hatch_events'] as $event) {
                $this->postJson("/api/flock-incubations/{$id}/hatch-events", $event)->assertCreated();
            }

            $this->assertDatabaseHas('flock_incubations', [
                'id' => $id,
                'species' => $spec['top']['species'],
                'egg_count' => $spec['top']['egg_count'],
            ]);
        }

        $this->assertDatabaseCount('flock_incubations', 4);
        $this->assertDatabaseCount('hatch_events', 2);
    }
}
