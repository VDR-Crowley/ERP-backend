<?php

namespace App\Services;

use App\Exceptions\RailwayApiException;
use App\Exceptions\RailwayNotConfiguredException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Consumo/custo do projeto no Railway no ciclo de cobrança atual, via Railway
 * Public API (GraphQL). A API devolve consumo bruto (vCPU-min, GB-min, GB);
 * o custo em US$ é calculado aqui com os preços de config('services.railway.prices_usd').
 *
 * Sem `startDate`, `usage`/`estimatedUsage` da API já usam o ciclo de cobrança
 * atual do dono do projeto — por isso não passamos datas.
 */
class RailwayUsageService
{
    /** Medidas pedidas à API, na ordem em que aparecem na resposta. */
    public const MEASUREMENTS = [
        'CPU_USAGE',
        'MEMORY_USAGE_GB',
        'NETWORK_TX_GB',
        'DISK_USAGE_GB',
        'BACKUP_USAGE_GB',
    ];

    /** Nome da chave na nossa resposta pra cada medida da API (com unidade). */
    private const KEYS = [
        'CPU_USAGE' => 'cpu_vcpu_minutes',
        'MEMORY_USAGE_GB' => 'memory_gb_minutes',
        'NETWORK_TX_GB' => 'network_egress_gb',
        'DISK_USAGE_GB' => 'volume_gb_minutes',
        'BACKUP_USAGE_GB' => 'backup_gb_minutes',
    ];

    /**
     * @return array{data: array<string, mixed>, cached: bool}
     *
     * @throws RailwayNotConfiguredException
     * @throws RailwayApiException
     */
    public function currentUsage(bool $fresh = false): array
    {
        $token = config('services.railway.token');
        $projectId = config('services.railway.project_id');

        $missing = array_keys(array_filter([
            'RAILWAY_API_TOKEN' => blank($token),
            'RAILWAY_PROJECT_ID' => blank($projectId),
        ]));
        if ($missing !== []) {
            throw new RailwayNotConfiguredException($missing);
        }

        $cacheKey = 'railway-usage:'.$projectId;
        $ttl = max(0, (int) config('services.railway.cache_seconds', 300));

        if (! $fresh && $ttl > 0 && ($hit = Cache::get($cacheKey)) !== null) {
            return ['data' => $hit, 'cached' => true];
        }

        $data = $this->build((string) $projectId);

        if ($ttl > 0) {
            Cache::put($cacheKey, $data, $ttl);
        }

        return ['data' => $data, 'cached' => false];
    }

    /** @return array<string, mixed> */
    private function build(string $projectId): array
    {
        $measurements = implode(', ', self::MEASUREMENTS);

        $usage = $this->query(
            "query(\$projectId: String!) {
                usage(projectId: \$projectId, measurements: [{$measurements}], groupBy: [SERVICE_ID]) {
                    measurement value tags { serviceId }
                }
                estimatedUsage(projectId: \$projectId, measurements: [{$measurements}]) {
                    measurement estimatedValue
                }
            }",
            ['projectId' => $projectId],
        );

        // Nomes dos serviços: consulta separada e não-fatal — se o token não
        // puder ler o projeto, a tela mostra o ID no lugar do nome.
        $names = [];
        $projectName = null;
        try {
            $project = $this->query(
                'query($id: String!) { project(id: $id) { name services { edges { node { id name } } } } }',
                ['id' => $projectId],
            )['project'] ?? null;
            $projectName = $project['name'] ?? null;
            foreach ($project['services']['edges'] ?? [] as $edge) {
                $names[$edge['node']['id']] = $edge['node']['name'];
            }
        } catch (RailwayApiException) {
            // segue sem nomes
        }

        $prices = config('services.railway.prices_usd', []);

        $services = [];
        foreach ($usage['usage'] ?? [] as $row) {
            $measurement = $row['measurement'] ?? null;
            if (! isset(self::KEYS[$measurement])) {
                continue;
            }
            $serviceId = $row['tags']['serviceId'] ?? null;
            $id = $serviceId ?? 'sem-servico';

            $services[$id] ??= [
                'service_id' => $serviceId,
                'name' => $serviceId !== null ? ($names[$serviceId] ?? $serviceId) : 'Sem serviço (projeto)',
                'usage' => array_fill_keys(array_values(self::KEYS), 0.0),
                'cost_usd' => 0.0,
            ];
            $value = (float) ($row['value'] ?? 0);
            $services[$id]['usage'][self::KEYS[$measurement]] += $value;
            $services[$id]['cost_usd'] += $value * (float) ($prices[$measurement] ?? 0);
        }

        $services = array_values($services);
        foreach ($services as &$service) {
            $service['cost_usd'] = round($service['cost_usd'], 4);
        }
        unset($service);
        usort($services, fn ($a, $b) => $b['cost_usd'] <=> $a['cost_usd']);

        $estimated = array_fill_keys(array_values(self::KEYS), 0.0);
        $estimatedCost = 0.0;
        foreach ($usage['estimatedUsage'] ?? [] as $row) {
            $measurement = $row['measurement'] ?? null;
            if (! isset(self::KEYS[$measurement])) {
                continue;
            }
            $value = (float) ($row['estimatedValue'] ?? 0);
            $estimated[self::KEYS[$measurement]] += $value;
            $estimatedCost += $value * (float) ($prices[$measurement] ?? 0);
        }

        $currentCost = array_sum(array_column($services, 'cost_usd'));
        $credit = (float) config('services.railway.plan_credit_usd', 1);

        return [
            'project' => ['id' => $projectId, 'name' => $projectName],
            'period' => 'current_billing_cycle',
            'currency' => 'USD',
            'plan_credit_usd' => $credit,
            'totals' => [
                'current_cost_usd' => round($currentCost, 4),
                'estimated_cycle_end_cost_usd' => round($estimatedCost, 4),
                'credit_used_percent' => $credit > 0 ? round($currentCost / $credit * 100, 2) : null,
                'estimated_credit_used_percent' => $credit > 0 ? round($estimatedCost / $credit * 100, 2) : null,
            ],
            'services' => $services,
            'estimated_usage' => $estimated,
            'prices_usd' => [
                'cpu_vcpu_minute' => (float) ($prices['CPU_USAGE'] ?? 0),
                'memory_gb_minute' => (float) ($prices['MEMORY_USAGE_GB'] ?? 0),
                'network_egress_gb' => (float) ($prices['NETWORK_TX_GB'] ?? 0),
                'volume_gb_minute' => (float) ($prices['DISK_USAGE_GB'] ?? 0),
                'backup_gb_minute' => (float) ($prices['BACKUP_USAGE_GB'] ?? 0),
            ],
            'note' => 'Custo calculado a partir do consumo bruto da Railway API × preço por unidade (config). Pode divergir centavos do painel da Railway, que é a fonte oficial da fatura.',
            'fetched_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     *
     * @throws RailwayApiException
     */
    private function query(string $query, array $variables): array
    {
        try {
            $response = Http::withToken((string) config('services.railway.token'))
                ->acceptJson()
                ->timeout((int) config('services.railway.timeout', 10))
                ->post((string) config('services.railway.endpoint'), [
                    'query' => $query,
                    'variables' => $variables,
                ]);
        } catch (ConnectionException $e) {
            throw new RailwayApiException('Não foi possível conectar na Railway API.', previous: $e);
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new RailwayApiException('A Railway recusou o RAILWAY_API_TOKEN (inválido, expirado ou sem acesso ao projeto).');
        }

        if ($response->failed()) {
            throw new RailwayApiException("A Railway API respondeu HTTP {$response->status()}.");
        }

        $errors = $response->json('errors');
        if (! empty($errors)) {
            $message = (string) ($errors[0]['message'] ?? 'erro desconhecido');
            throw new RailwayApiException("A Railway API retornou erro: {$message}");
        }

        $data = $response->json('data');
        if (! is_array($data)) {
            throw new RailwayApiException('Resposta inesperada da Railway API (sem `data`).');
        }

        return $data;
    }
}
