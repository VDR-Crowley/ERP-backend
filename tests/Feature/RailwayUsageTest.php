<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vendedor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** GET /api/admin/railway-usage — consumo/custo do Railway, só ADMINISTRADOR. */
class RailwayUsageTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/admin/railway-usage';

    private const ENDPOINT = 'https://railway.test/graphql/v2';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.railway.token' => 'test-railway-token',
            'services.railway.project_id' => 'proj-1',
            'services.railway.endpoint' => self::ENDPOINT,
            'services.railway.cache_seconds' => 300,
            'services.railway.plan_credit_usd' => 1.0,
            'services.railway.prices_usd' => [
                'CPU_USAGE' => 0.001,
                'MEMORY_USAGE_GB' => 0.0002,
                'NETWORK_TX_GB' => 0.05,
                'DISK_USAGE_GB' => 0.00001,
                'BACKUP_USAGE_GB' => 0.00001,
            ],
        ]);
    }

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMINISTRADOR]);
        Sanctum::actingAs($user, ['access']);

        return $user;
    }

    private function fakeRailway(): void
    {
        Http::fake([
            self::ENDPOINT => function (HttpRequest $request) {
                if (str_contains($request['query'], 'usage(')) {
                    return Http::response(['data' => [
                        'usage' => [
                            ['measurement' => 'CPU_USAGE', 'value' => 100, 'tags' => ['serviceId' => 'svc-app']],
                            ['measurement' => 'MEMORY_USAGE_GB', 'value' => 1000, 'tags' => ['serviceId' => 'svc-app']],
                            ['measurement' => 'NETWORK_TX_GB', 'value' => 2, 'tags' => ['serviceId' => 'svc-app']],
                            ['measurement' => 'MEMORY_USAGE_GB', 'value' => 500, 'tags' => ['serviceId' => 'svc-pg']],
                            ['measurement' => 'DISK_USAGE_GB', 'value' => 10000, 'tags' => ['serviceId' => 'svc-pg']],
                        ],
                        'estimatedUsage' => [
                            ['measurement' => 'CPU_USAGE', 'estimatedValue' => 300],
                            ['measurement' => 'MEMORY_USAGE_GB', 'estimatedValue' => 4500],
                        ],
                    ]]);
                }

                return Http::response(['data' => ['project' => [
                    'name' => 'ERP',
                    'services' => ['edges' => [
                        ['node' => ['id' => 'svc-app', 'name' => 'App']],
                        ['node' => ['id' => 'svc-pg', 'name' => 'Postgres']],
                    ]],
                ]]]);
            },
        ]);
    }

    public function test_guest_gets_401(): void
    {
        $this->getJson(self::URL)->assertUnauthorized();
    }

    public function test_vendedor_gets_403(): void
    {
        $vendedor = Vendedor::factory()->create();
        $user = User::factory()->create(['role' => User::ROLE_VENDEDOR, 'vendedor_id' => $vendedor->id]);
        Sanctum::actingAs($user, ['access']);
        Http::fake();

        $this->getJson(self::URL)->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_vendedor_role_without_vendedor_id_gets_403(): void
    {
        // RestrictVendedor deixa passar (isVendedor exige vendedor_id); o
        // middleware `admin` precisa barrar mesmo assim.
        $user = User::factory()->create(['role' => User::ROLE_VENDEDOR, 'vendedor_id' => null]);
        Sanctum::actingAs($user, ['access']);
        Http::fake();

        $this->getJson(self::URL)->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_refresh_token_cannot_access(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMINISTRADOR]);
        Sanctum::actingAs($user, ['refresh']);

        $this->getJson(self::URL)->assertForbidden();
    }

    public function test_missing_token_returns_503_with_instructions(): void
    {
        config(['services.railway.token' => null]);
        $this->actingAsAdmin();
        Http::fake();

        $this->getJson(self::URL)
            ->assertStatus(503)
            ->assertJsonPath('configured', false)
            ->assertJsonPath('missing', ['RAILWAY_API_TOKEN'])
            ->assertJsonStructure(['message', 'how_to_fix']);
        Http::assertNothingSent();
    }

    public function test_missing_project_id_returns_503(): void
    {
        config(['services.railway.project_id' => '']);
        $this->actingAsAdmin();

        $this->getJson(self::URL)
            ->assertStatus(503)
            ->assertJsonPath('missing', ['RAILWAY_PROJECT_ID']);
    }

    public function test_admin_gets_usage_and_cost_per_service(): void
    {
        $this->actingAsAdmin();
        $this->fakeRailway();

        $response = $this->getJson(self::URL)->assertOk();

        // App: 100*0.001 + 1000*0.0002 + 2*0.05 = 0.1 + 0.2 + 0.1 = 0.4
        // Postgres: 500*0.0002 + 10000*0.00001 = 0.1 + 0.1 = 0.2
        $response
            ->assertJsonPath('configured', true)
            ->assertJsonPath('cached', false)
            ->assertJsonPath('project.name', 'ERP')
            ->assertJsonPath('currency', 'USD')
            ->assertJsonPath('services.0.name', 'App')
            ->assertJsonPath('services.0.usage.cpu_vcpu_minutes', 100)
            ->assertJsonPath('services.0.usage.network_egress_gb', 2)
            ->assertJsonPath('services.1.name', 'Postgres')
            ->assertJsonPath('services.1.usage.volume_gb_minutes', 10000);

        $this->assertEqualsWithDelta(0.4, $response->json('services.0.cost_usd'), 1e-9);
        $this->assertEqualsWithDelta(0.2, $response->json('services.1.cost_usd'), 1e-9);
        $this->assertEqualsWithDelta(0.6, $response->json('totals.current_cost_usd'), 1e-9);
        // estimado: 300*0.001 + 4500*0.0002 = 0.3 + 0.9 = 1.2
        $this->assertEqualsWithDelta(1.2, $response->json('totals.estimated_cycle_end_cost_usd'), 1e-9);
        $this->assertEqualsWithDelta(60.0, $response->json('totals.credit_used_percent'), 1e-9);
        $this->assertEqualsWithDelta(120.0, $response->json('totals.estimated_credit_used_percent'), 1e-9);

        Http::assertSent(fn (HttpRequest $r) => $r->hasHeader('Authorization', 'Bearer test-railway-token')
            && $r['variables'] === ['projectId' => 'proj-1']);
    }

    public function test_result_is_cached_and_fresh_bypasses_cache(): void
    {
        $this->actingAsAdmin();
        $this->fakeRailway();

        $this->getJson(self::URL)->assertOk()->assertJsonPath('cached', false);
        $this->getJson(self::URL)->assertOk()->assertJsonPath('cached', true);
        Http::assertSentCount(2); // usage + project, só na 1ª chamada

        $this->getJson(self::URL.'?fresh=1')->assertOk()->assertJsonPath('cached', false);
        Http::assertSentCount(4);
    }

    public function test_service_names_fall_back_to_ids_when_project_query_fails(): void
    {
        $this->actingAsAdmin();
        Http::fake([
            self::ENDPOINT => function (HttpRequest $request) {
                if (str_contains($request['query'], 'usage(')) {
                    return Http::response(['data' => [
                        'usage' => [['measurement' => 'CPU_USAGE', 'value' => 10, 'tags' => ['serviceId' => 'svc-x']]],
                        'estimatedUsage' => [],
                    ]]);
                }

                return Http::response(['data' => null, 'errors' => [['message' => 'Not Authorized']]]);
            },
        ]);

        $this->getJson(self::URL)
            ->assertOk()
            ->assertJsonPath('project.name', null)
            ->assertJsonPath('services.0.name', 'svc-x');
    }

    public function test_rejected_token_returns_502(): void
    {
        $this->actingAsAdmin();
        Http::fake([self::ENDPOINT => Http::response(['errors' => [['message' => 'Unauthorized']]], 401)]);

        $this->getJson(self::URL)
            ->assertStatus(502)
            ->assertJsonPath('configured', true)
            ->assertJsonFragment(['message' => 'A Railway recusou o RAILWAY_API_TOKEN (inválido, expirado ou sem acesso ao projeto).']);
    }

    public function test_graphql_error_returns_502(): void
    {
        $this->actingAsAdmin();
        Http::fake([self::ENDPOINT => Http::response(['data' => null, 'errors' => [['message' => 'Problem processing request']]])]);

        $this->getJson(self::URL)
            ->assertStatus(502)
            ->assertJsonFragment(['message' => 'A Railway API retornou erro: Problem processing request']);
    }

    public function test_token_is_never_leaked_in_response(): void
    {
        $this->actingAsAdmin();
        $this->fakeRailway();

        $this->assertStringNotContainsString('test-railway-token', $this->getJson(self::URL)->getContent());
    }
}
