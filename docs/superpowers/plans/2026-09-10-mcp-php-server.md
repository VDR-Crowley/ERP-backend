# Servidor MCP em PHP (ERP-Backend) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Servidor MCP de leitura em PHP, vivendo dentro do `ERP-Backend` (Laravel), espelhando as 29 tools do servidor Node em `../ERP-MCP` — mesmos nomes/descrições/comportamento, GET-only forçado, config via env, testes PHPUnit, doc em `docs/MCP.md`.

**Architecture:** SDK oficial `mcp/sdk` (pacote Packagist do repo `modelcontextprotocol/php-sdk`). Um array PHP de definições (`App\Mcp\ToolDefinitions`) é a fonte única de verdade, registrado em loop via `Server::builder()->addTool(...)` (API programática do SDK, não descoberta por atributos). Um client HTTP GET-only (`App\Mcp\ErpApiClient`, sobre o `Http` facade do Laravel) chama a API real. Comando artisan `mcp:serve` sobe o transporte STDIO. **Não mexe em `../ERP-MCP`.**

**Tech Stack:** Laravel 13 / PHP 8.4, `mcp/sdk` (Packagist), `Illuminate\Support\Facades\Http`, PHPUnit 12 (`php artisan test`).

## Global Constraints

- Não alterar nada em `/Users/vandodosreis/projects/ERP/ERP-MCP` (projeto Node).
- 29 tools, uma por endpoint GET de `docs/openapi.yaml` — mesmos nomes/descrições do Node (`src/tools/definitions.ts`), não inventar nem remover nenhuma.
- `App\Mcp\ErpApiClient` expõe só `get()` publicamente; verbo != GET é recusado internamente mesmo que nada hoje chame com outro verbo (defesa em profundidade).
- Nunca escrever em STDOUT fora do protocolo JSON-RPC — avisos/logs do comando `mcp:serve` vão sempre pra STDERR (`fwrite(STDERR, ...)`).
- Erros não-2xx da API devem chegar ao cliente MCP com o corpo/status reais (via `Mcp\Exception\ToolCallException`), não uma mensagem genérica.
- Testes em `tests/Unit/Mcp/`, estendendo `Tests\TestCase`, usando `Http::fake()` — nunca bater na API real.
- `composer.json` ganha `mcp/sdk` como dependência real (via `composer require`), não hardcoded manualmente.
- Documentação nova em `docs/MCP.md`, linkada do `README.md`; exemplos de config usam a URL real de produção `https://laravel-production-4c67.up.railway.app/api` (não só placeholder genérico) e o comando `curl` de login exato contra essa URL.
- Commit local ao final de cada task. Push só depois de confirmar a política de push do repo com o usuário.

---

### Task 1: Instalar o SDK oficial e confirmar que resolve

**Files:**
- Modify: `composer.json`, `composer.lock`

**Interfaces:**
- Produces: dependência `mcp/sdk` disponível via autoload (`Mcp\...` namespace) pras próximas tasks.

- [ ] **Step 1: Instalar o pacote**

Run: `composer require mcp/sdk`

Expected: composer resolve e trava uma versão estável (`^0.8.x` no momento deste plano — deixa o composer escolher, não fixar manualmente), `composer.json`/`composer.lock` atualizados.

- [ ] **Step 2: Confirmar autoload da classe raiz do SDK**

Run: `php -r "require 'vendor/autoload.php'; var_dump(class_exists(\Mcp\Server::class));"`

Expected: `bool(true)`

- [ ] **Step 3: Confirmar que a suíte de testes do projeto continua passando antes de mexer em mais nada**

Run: `composer test`

Expected: suíte existente passa igual (nenhum teste quebrado só de instalar a dependência).

- [ ] **Step 4: Commit**

```bash
git add composer.json composer.lock
git commit -m "chore: instala mcp/sdk (SDK oficial MCP em PHP)

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 2: Config do MCP (`config/mcp.php` + `App\Mcp\McpConfig`)

**Files:**
- Create: `app/Mcp/McpConfig.php`
- Create: `config/mcp.php`
- Modify: `phpunit.xml` (env fixos de teste)
- Test: `tests/Unit/Mcp/McpConfigTest.php`

**Interfaces:**
- Produces: `App\Mcp\McpConfig::resolveBaseUrl(?string $envBaseUrl, ?string $appUrl): string`, `App\Mcp\McpConfig::resolveToken(?string $envToken): ?string` — usados por `config/mcp.php` e testáveis sem precisar re-bootar o Laravel com envs diferentes. `config('mcp.base_url')` e `config('mcp.token')` — consumidos pela Task 3 (`ErpApiClient`).

- [ ] **Step 1: Escrever o teste (falha primeiro)**

Create `tests/Unit/Mcp/McpConfigTest.php`:

```php
<?php

namespace Tests\Unit\Mcp;

use App\Mcp\McpConfig;
use Tests\TestCase;

class McpConfigTest extends TestCase
{
    public function test_resolve_base_url_uses_env_when_set(): void
    {
        $this->assertSame(
            'https://laravel-production-4c67.up.railway.app/api',
            McpConfig::resolveBaseUrl('https://laravel-production-4c67.up.railway.app/api/', 'http://localhost'),
        );
    }

    public function test_resolve_base_url_falls_back_to_app_url_when_env_unset(): void
    {
        $this->assertSame('http://localhost/api', McpConfig::resolveBaseUrl(null, 'http://localhost'));
    }

    public function test_resolve_base_url_falls_back_to_app_url_when_env_blank(): void
    {
        $this->assertSame('http://localhost/api', McpConfig::resolveBaseUrl('   ', 'http://localhost'));
    }

    public function test_resolve_base_url_strips_trailing_slash(): void
    {
        $this->assertSame('http://localhost/api', McpConfig::resolveBaseUrl(null, 'http://localhost/'));
    }

    public function test_resolve_token_trims_whitespace(): void
    {
        $this->assertSame('abc123', McpConfig::resolveToken(' abc123 '));
    }

    public function test_resolve_token_returns_null_when_unset(): void
    {
        $this->assertNull(McpConfig::resolveToken(null));
    }

    public function test_resolve_token_treats_blank_as_null(): void
    {
        $this->assertNull(McpConfig::resolveToken('   '));
    }

    public function test_config_file_is_wired_to_env(): void
    {
        $this->assertSame('https://mcp-test.example/api', config('mcp.base_url'));
        $this->assertSame('test-token-123', config('mcp.token'));
    }
}
```

- [ ] **Step 2: Rodar e confirmar que falha (classe não existe ainda)**

Run: `php artisan test tests/Unit/Mcp/McpConfigTest.php`
Expected: FAIL — `Class "App\Mcp\McpConfig" not found`

- [ ] **Step 3: Implementar `App\Mcp\McpConfig`**

Create `app/Mcp/McpConfig.php`:

```php
<?php

namespace App\Mcp;

/**
 * Lógica pura de resolução da config do servidor MCP, extraída do
 * config/mcp.php pra ser testável sem depender do boot do Laravel com envs
 * diferentes (config files só rodam uma vez por processo).
 */
final class McpConfig
{
    public static function resolveBaseUrl(?string $envBaseUrl, ?string $appUrl): string
    {
        $trimmedEnv = $envBaseUrl !== null ? trim($envBaseUrl) : '';

        $raw = $trimmedEnv !== ''
            ? $trimmedEnv
            : rtrim((string) $appUrl, '/').'/api';

        return rtrim($raw, '/');
    }

    public static function resolveToken(?string $envToken): ?string
    {
        $trimmed = $envToken !== null ? trim($envToken) : '';

        return $trimmed !== '' ? $trimmed : null;
    }
}
```

- [ ] **Step 4: Criar `config/mcp.php`**

Create `config/mcp.php`:

```php
<?php

use App\Mcp\McpConfig;

/*
|--------------------------------------------------------------------------
| Config do servidor MCP (PHP)
|--------------------------------------------------------------------------
|
| ERP_API_BASE_URL: base da API do MiniERP (sem barra final). Sem essa env
| setada, cai pro próprio APP_URL local + "/api" — esse servidor já mora
| dentro do backend, então rodar local sem configurar nada aponta pra ele
| mesmo. Pra apontar pra produção:
|   ERP_API_BASE_URL=https://laravel-production-4c67.up.railway.app/api
|
| ERP_API_TOKEN: token de acesso Sanctum (Bearer). Ver docs/MCP.md "Como
| obter um token".
|
*/

return [
    'base_url' => McpConfig::resolveBaseUrl(env('ERP_API_BASE_URL'), env('APP_URL')),
    'token' => McpConfig::resolveToken(env('ERP_API_TOKEN')),
];
```

- [ ] **Step 5: Adicionar env fixos de teste no `phpunit.xml`**

In `phpunit.xml`, inside the existing `<php>` block, add two lines (keep everything else in that block untouched):

```xml
        <env name="ERP_API_BASE_URL" value="https://mcp-test.example/api"/>
        <env name="ERP_API_TOKEN" value="test-token-123"/>
```

- [ ] **Step 6: Rodar e confirmar que passa**

Run: `php artisan test tests/Unit/Mcp/McpConfigTest.php`
Expected: PASS (8 testes)

- [ ] **Step 7: Commit**

```bash
git add app/Mcp/McpConfig.php config/mcp.php phpunit.xml tests/Unit/Mcp/McpConfigTest.php
git commit -m "feat: config do servidor MCP em PHP (ERP_API_BASE_URL/ERP_API_TOKEN)

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 3: Exceções do client (`MissingTokenException`, `ApiUnreachableException`, `ErpApiException`)

**Files:**
- Create: `app/Mcp/MissingTokenException.php`
- Create: `app/Mcp/ApiUnreachableException.php`
- Create: `app/Mcp/ErpApiException.php`

**Interfaces:**
- Consumes: nada (classes puras).
- Produces: `App\Mcp\MissingTokenException` (sem args), `App\Mcp\ApiUnreachableException(string $url, \Throwable $cause)`, `App\Mcp\ErpApiException(string $message, int $status, string $url, mixed $body)` (com `$status`, `$url`, `$body` públicos e readonly) — consumidos pela Task 4 (`ErpApiClient`).

Sem teste dedicado nesta task — são cobertas pelos testes de `ErpApiClient` na Task 4 (que são o consumidor real). Isso evita testar "que uma exceção guarda o que foi passado no construtor", que não carrega comportamento próprio.

- [ ] **Step 1: Criar as três classes**

Create `app/Mcp/MissingTokenException.php`:

```php
<?php

namespace App\Mcp;

/** Lançada quando ERP_API_TOKEN não está configurado, antes de qualquer tentativa de request. */
class MissingTokenException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            'ERP_API_TOKEN não está configurado. Esse servidor precisa de um token de acesso Sanctum '.
            'válido pra chamar a API do MiniERP — veja docs/MCP.md "Como obter um token".',
        );
    }
}
```

Create `app/Mcp/ApiUnreachableException.php`:

```php
<?php

namespace App\Mcp;

/** Lançada quando a API do MiniERP não pôde ser alcançada (DNS, conexão recusada, timeout, ...). */
class ApiUnreachableException extends \RuntimeException
{
    public function __construct(string $url, \Throwable $cause)
    {
        parent::__construct(
            "Não foi possível alcançar a API do MiniERP em {$url}: {$cause->getMessage()}",
            previous: $cause,
        );
    }
}
```

Create `app/Mcp/ErpApiException.php`:

```php
<?php

namespace App\Mcp;

/**
 * Lançada pra qualquer resposta não-2xx da API. Carrega o corpo/status reais
 * (JSON parseado quando possível, texto cru senão) pra quem chamar poder
 * expor a mensagem de validação/auth real em vez de uma falha genérica.
 */
class ErpApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly string $url,
        public readonly mixed $body,
    ) {
        parent::__construct($message);
    }
}
```

- [ ] **Step 2: Confirmar que a suíte ainda passa (nada quebrou)**

Run: `php artisan test tests/Unit/Mcp`
Expected: PASS (só o `McpConfigTest` existe até aqui, continua passando)

- [ ] **Step 3: Commit**

```bash
git add app/Mcp/MissingTokenException.php app/Mcp/ApiUnreachableException.php app/Mcp/ErpApiException.php
git commit -m "feat: exceções do client MCP (token ausente, API inacessível, erro não-2xx)

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 4: `App\Mcp\ErpApiClient` (GET-only, header de auth, URL/query, exposição de erro)

**Files:**
- Create: `app/Mcp/ErpApiClient.php`
- Test: `tests/Unit/Mcp/ErpApiClientTest.php`

**Interfaces:**
- Consumes: `config('mcp.base_url')`, `config('mcp.token')` (Task 2); `App\Mcp\MissingTokenException`, `App\Mcp\ApiUnreachableException`, `App\Mcp\ErpApiException` (Task 3).
- Produces: `App\Mcp\ErpApiClient::get(string $path, array $pathParams = [], array $query = []): array` — único método público, consumido pela Task 6 (`McpServerFactory`).

- [ ] **Step 1: Escrever os testes (falha primeiro)**

Create `tests/Unit/Mcp/ErpApiClientTest.php`:

```php
<?php

namespace Tests\Unit\Mcp;

use App\Mcp\ApiUnreachableException;
use App\Mcp\ErpApiClient;
use App\Mcp\ErpApiException;
use App\Mcp\MissingTokenException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ErpApiClientTest extends TestCase
{
    public function test_only_exposes_get_publicly(): void
    {
        $publicMethods = (new \ReflectionClass(ErpApiClient::class))->getMethods(\ReflectionMethod::IS_PUBLIC);
        $names = array_map(fn (\ReflectionMethod $m) => $m->getName(), $publicMethods);

        $this->assertSame(['get'], array_values(array_diff($names, ['__construct'])));
    }

    public function test_internal_request_refuses_non_get_verbs(): void
    {
        Http::fake();

        $client = new ErpApiClient();
        $request = new \ReflectionMethod(ErpApiClient::class, 'request');
        $request->setAccessible(true);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/somente leitura/');

        $request->invoke($client, 'POST', '/sales', [], []);
    }

    public function test_missing_token_fails_before_any_request(): void
    {
        Config::set('mcp.token', null);
        Http::fake();

        try {
            (new ErpApiClient())->get('/sales');
            $this->fail('Esperava MissingTokenException');
        } catch (MissingTokenException $e) {
            Http::assertNothingSent();
        }
    }

    public function test_sends_bearer_token_header(): void
    {
        Config::set('mcp.token', 'abc123');
        Config::set('mcp.base_url', 'https://mcp-test.example/api');
        Http::fake(['*' => Http::response(['id' => 1], 200)]);

        (new ErpApiClient())->get('/sales/1');

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer abc123'));
    }

    public function test_builds_url_with_path_params(): void
    {
        Config::set('mcp.token', 'abc123');
        Config::set('mcp.base_url', 'https://mcp-test.example/api');
        Http::fake(['*' => Http::response([], 200)]);

        (new ErpApiClient())->get('/sales/{sale}', ['sale' => 42]);

        Http::assertSent(fn ($request) => $request->url() === 'https://mcp-test.example/api/sales/42');
    }

    public function test_builds_query_string_omitting_null_values(): void
    {
        Config::set('mcp.token', 'abc123');
        Config::set('mcp.base_url', 'https://mcp-test.example/api');
        Http::fake(['*' => Http::response([], 200)]);

        (new ErpApiClient())->get('/business-line-report', [], ['start' => '2026-01-01', 'end' => null]);

        Http::assertSent(fn ($request) => $request->url() === 'https://mcp-test.example/api/business-line-report?start=2026-01-01');
    }

    public function test_missing_path_param_throws(): void
    {
        Config::set('mcp.token', 'abc123');
        Config::set('mcp.base_url', 'https://mcp-test.example/api');
        Http::fake();

        $this->expectException(\InvalidArgumentException::class);

        (new ErpApiClient())->get('/sales/{sale}', []);
    }

    public function test_exposes_401_body_and_message(): void
    {
        Config::set('mcp.token', 'expired-token');
        Config::set('mcp.base_url', 'https://mcp-test.example/api');
        Http::fake(['*' => Http::response(['message' => 'Unauthenticated.'], 401)]);

        try {
            (new ErpApiClient())->get('/sales');
            $this->fail('Esperava ErpApiException');
        } catch (ErpApiException $e) {
            $this->assertSame(401, $e->status);
            $this->assertStringContainsString('Unauthenticated.', $e->getMessage());
            $this->assertStringContainsString('expiram em 2h', $e->getMessage());
            $this->assertSame(['message' => 'Unauthenticated.'], $e->body);
        }
    }

    public function test_exposes_404_body_and_message(): void
    {
        Config::set('mcp.token', 'abc123');
        Config::set('mcp.base_url', 'https://mcp-test.example/api');
        Http::fake(['*' => Http::response(['message' => 'No query results for model.'], 404)]);

        try {
            (new ErpApiClient())->get('/sales/{sale}', ['sale' => 999]);
            $this->fail('Esperava ErpApiException');
        } catch (ErpApiException $e) {
            $this->assertSame(404, $e->status);
            $this->assertStringContainsString('não existe', $e->getMessage());
            $this->assertStringContainsString('No query results for model.', $e->getMessage());
        }
    }

    public function test_exposes_full_body_for_other_status_codes(): void
    {
        Config::set('mcp.token', 'abc123');
        Config::set('mcp.base_url', 'https://mcp-test.example/api');
        Http::fake(['*' => Http::response('Bad Gateway', 502)]);

        try {
            (new ErpApiClient())->get('/sales');
            $this->fail('Esperava ErpApiException');
        } catch (ErpApiException $e) {
            $this->assertSame(502, $e->status);
            $this->assertStringContainsString('Bad Gateway', $e->getMessage());
        }
    }

    public function test_exposes_network_unreachable(): void
    {
        Config::set('mcp.token', 'abc123');
        Config::set('mcp.base_url', 'https://mcp-test.example/api');
        Http::fake(['*' => fn () => throw new ConnectionException('Connection refused')]);

        $this->expectException(ApiUnreachableException::class);
        $this->expectExceptionMessageMatches('/Connection refused/');

        (new ErpApiClient())->get('/sales');
    }
}
```

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `php artisan test tests/Unit/Mcp/ErpApiClientTest.php`
Expected: FAIL — `Class "App\Mcp\ErpApiClient" not found`

- [ ] **Step 3: Implementar `App\Mcp\ErpApiClient`**

Create `app/Mcp/ErpApiClient.php`:

```php
<?php

namespace App\Mcp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Client HTTP pra API do MiniERP. GET-only por construção: `get()` é o
 * único método público, e o `request()` interno recusa qualquer verbo
 * diferente de GET — defesa em profundidade, mesmo que hoje nada no código
 * chame com outro verbo (mesmo desenho do client Node em ../ERP-MCP).
 */
class ErpApiClient
{
    /**
     * @param array<string, int|string>            $pathParams
     * @param array<string, int|string|bool|null>  $query
     */
    public function get(string $path, array $pathParams = [], array $query = []): array
    {
        return $this->request('GET', $path, $pathParams, $query);
    }

    /**
     * @param array<string, int|string>           $pathParams
     * @param array<string, int|string|bool|null> $query
     */
    private function request(string $method, string $path, array $pathParams, array $query): array
    {
        if ('GET' !== $method) {
            throw new \LogicException(
                "ErpApiClient é somente leitura: recusou emitir uma requisição {$method} ".
                '(isso é um bug no código, não input do usuário).',
            );
        }

        $token = config('mcp.token');
        if (empty($token)) {
            throw new MissingTokenException();
        }

        $url = $this->buildUrl($path, $pathParams, $query);

        try {
            $response = Http::withToken($token)->acceptJson()->get($url);
        } catch (ConnectionException $e) {
            throw new ApiUnreachableException($url, $e);
        }

        if ($response->failed()) {
            $body = $response->json() ?? $response->body();

            throw new ErpApiException(
                $this->formatErrorMessage($response->status(), $body),
                $response->status(),
                $url,
                $body,
            );
        }

        return $response->json() ?? [];
    }

    /**
     * @param array<string, int|string>           $pathParams
     * @param array<string, int|string|bool|null> $query
     */
    private function buildUrl(string $path, array $pathParams, array $query): string
    {
        $resolvedPath = preg_replace_callback(
            '/\{([^}]+)\}/',
            function (array $matches) use ($pathParams, $path) {
                $name = $matches[1];
                $value = $pathParams[$name] ?? null;

                if (null === $value || '' === $value) {
                    throw new \InvalidArgumentException("Parâmetro de path obrigatório \"{$name}\" faltando pra {$path}");
                }

                return rawurlencode((string) $value);
            },
            $path,
        );

        $baseUrl = rtrim((string) config('mcp.base_url'), '/');
        $url = $baseUrl.$resolvedPath;

        $query = array_filter($query, static fn ($value) => null !== $value);

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }

    private function formatErrorMessage(int $status, mixed $body): string
    {
        $bodyMessage = \is_array($body) && \is_string($body['message'] ?? null) ? $body['message'] : null;
        $base = "API do MiniERP retornou {$status}";

        if (401 === $status) {
            return "{$base}. Token ausente, inválido, expirado ou revogado (access tokens expiram em 2h) — ".
                'pegue um novo (veja docs/MCP.md "Como obter um token").'.
                ($bodyMessage ? " Mensagem da API: {$bodyMessage}" : '');
        }

        if (403 === $status) {
            return "{$base}. Token autenticado mas sem a ability \"access\" pra essa rota ".
                '(ex.: um refresh token foi usado aqui).'.
                ($bodyMessage ? " Mensagem da API: {$bodyMessage}" : '');
        }

        if (404 === $status) {
            return "{$base}. O recurso pedido não existe.".
                ($bodyMessage ? " Mensagem da API: {$bodyMessage}" : '');
        }

        return "{$base}.".($bodyMessage ? " Mensagem da API: {$bodyMessage}" : '').
            ' Corpo completo da resposta: '.json_encode($body);
    }
}
```

- [ ] **Step 4: Rodar e confirmar que passa**

Run: `php artisan test tests/Unit/Mcp/ErpApiClientTest.php`
Expected: PASS (11 testes)

- [ ] **Step 5: Rodar a suíte inteira de Mcp**

Run: `php artisan test tests/Unit/Mcp`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Mcp/ErpApiClient.php tests/Unit/Mcp/ErpApiClientTest.php
git commit -m "feat: ErpApiClient GET-only com exposição de erro real da API

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 5: `App\Mcp\ToolDefinitions` (as 29 tools)

**Files:**
- Create: `app/Mcp/ToolDefinitions.php`
- Test: `tests/Unit/Mcp/ToolDefinitionsTest.php`

**Interfaces:**
- Produces: `App\Mcp\ToolDefinitions::all(): list<array{name: string, description: string, path: string, pathParams: list<array{name: string, description: string}>, queryParams: list<array{name: string, description: string}>}>` — consumido pela Task 6 (`McpServerFactory`).

- [ ] **Step 1: Escrever o teste (falha primeiro)**

Create `tests/Unit/Mcp/ToolDefinitionsTest.php`:

```php
<?php

namespace Tests\Unit\Mcp;

use App\Mcp\ToolDefinitions;
use Tests\TestCase;

class ToolDefinitionsTest extends TestCase
{
    private const EXPECTED_NAMES = [
        'get_current_user', 'list_users', 'list_products', 'get_product',
        'list_vendedores', 'get_vendedor', 'list_flock', 'get_flock',
        'list_flock_incubations', 'get_flock_incubation', 'list_hatch_events',
        'list_vendor_stock', 'get_vendor_stock', 'list_sales', 'get_sale',
        'list_stock_transfers', 'get_stock_transfer', 'list_daily_productions',
        'get_daily_production', 'list_expenses', 'get_expense', 'list_cash_flows',
        'get_cash_flow', 'list_feed_stocks', 'get_feed_stock', 'list_feed_open_logs',
        'list_flock_cleanings', 'get_flock_cleaning', 'get_business_line_report',
    ];

    public function test_has_exactly_29_tools(): void
    {
        $this->assertCount(29, ToolDefinitions::all());
    }

    public function test_names_match_expected_list_in_order(): void
    {
        $names = array_map(fn (array $def) => $def['name'], ToolDefinitions::all());

        $this->assertSame(self::EXPECTED_NAMES, $names);
    }

    public function test_names_are_unique(): void
    {
        $names = array_map(fn (array $def) => $def['name'], ToolDefinitions::all());

        $this->assertCount(\count($names), array_unique($names));
    }

    public function test_get_sale_has_required_path_param(): void
    {
        $def = $this->findByName('get_sale');

        $this->assertSame('/sales/{sale}', $def['path']);
        $this->assertSame([['name' => 'sale', 'description' => 'Sale ID']], $def['pathParams']);
        $this->assertSame([], $def['queryParams']);
    }

    public function test_get_business_line_report_has_start_end_query_params(): void
    {
        $def = $this->findByName('get_business_line_report');

        $this->assertSame('/business-line-report', $def['path']);
        $this->assertSame([], $def['pathParams']);
        $this->assertSame(['start', 'end'], array_column($def['queryParams'], 'name'));
    }

    public function test_list_hatch_events_scoped_under_flock_incubation(): void
    {
        $def = $this->findByName('list_hatch_events');

        $this->assertSame('/flock-incubations/{flock_incubation}/hatch-events', $def['path']);
        $this->assertSame([['name' => 'flock_incubation', 'description' => 'Flock incubation batch ID']], $def['pathParams']);
    }

    private function findByName(string $name): array
    {
        foreach (ToolDefinitions::all() as $def) {
            if ($def['name'] === $name) {
                return $def;
            }
        }

        $this->fail("Tool \"{$name}\" não encontrada");
    }
}
```

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `php artisan test tests/Unit/Mcp/ToolDefinitionsTest.php`
Expected: FAIL — `Class "App\Mcp\ToolDefinitions" not found`

- [ ] **Step 3: Implementar `App\Mcp\ToolDefinitions`**

Create `app/Mcp/ToolDefinitions.php`:

```php
<?php

namespace App\Mcp;

/**
 * Uma entrada por endpoint GET de ERP-Backend/docs/openapi.yaml — mesmos
 * nomes/descrições/paths do servidor Node em ../ERP-MCP
 * (src/tools/definitions.ts), hand-authored a partir do spec, não codegen
 * cego. queryParams aqui são sempre string opcional de data ISO
 * (YYYY-MM-DD) — hoje só start/end de get_business_line_report; se um dia
 * um query param não-data for adicionado, o schema builder (McpServerFactory)
 * precisa ser estendido, não só este array.
 *
 * IMPORTANTE: só endpoints GET entram aqui. Não adicionar POST/PUT/PATCH/
 * DELETE — este servidor é read-only por design.
 *
 * @phpstan-type PathParamDef array{name: string, description: string}
 * @phpstan-type QueryParamDef array{name: string, description: string}
 * @phpstan-type ToolDefinition array{
 *     name: string,
 *     description: string,
 *     path: string,
 *     pathParams: list<PathParamDef>,
 *     queryParams: list<QueryParamDef>,
 * }
 */
final class ToolDefinitions
{
    /**
     * @return list<ToolDefinition>
     */
    public static function all(): array
    {
        return [
            [
                'name' => 'get_current_user',
                'description' => 'Get the user data for the currently authenticated API token.',
                'path' => '/user',
                'pathParams' => [],
                'queryParams' => [],
            ],
            [
                'name' => 'list_users',
                'description' => 'List all admin-panel users (not paginated). Excludes the public self-registration flow.',
                'path' => '/users',
                'pathParams' => [],
                'queryParams' => [],
            ],
            [
                'name' => 'list_products',
                'description' => 'List all products sold (eggs, packaging, etc.).',
                'path' => '/products',
                'pathParams' => [],
                'queryParams' => [],
            ],
            [
                'name' => 'get_product',
                'description' => 'Get one product by ID.',
                'path' => '/products/{product}',
                'pathParams' => [['name' => 'product', 'description' => 'Product ID']],
                'queryParams' => [],
            ],
            [
                'name' => 'list_vendedores',
                'description' => 'List all vendedores (resellers/salespeople) registered in the system.',
                'path' => '/vendedores',
                'pathParams' => [],
                'queryParams' => [],
            ],
            [
                'name' => 'get_vendedor',
                'description' => 'Get one vendedor (reseller/salesperson) by ID.',
                'path' => '/vendedores/{vendedor}',
                'pathParams' => [['name' => 'vendedor', 'description' => 'Vendedor ID']],
                'queryParams' => [],
            ],
            [
                'name' => 'list_flock',
                'description' => 'List all flock/plantel batches (quail or chicken batches in production).',
                'path' => '/flock',
                'pathParams' => [],
                'queryParams' => [],
            ],
            [
                'name' => 'get_flock',
                'description' => 'Get one flock/plantel batch by ID.',
                'path' => '/flock/{flock}',
                'pathParams' => [['name' => 'flock', 'description' => 'Flock (plantel) batch ID']],
                'queryParams' => [],
            ],
            [
                'name' => 'list_flock_incubations',
                'description' => 'List all incubation batches, each including its hatch events.',
                'path' => '/flock-incubations',
                'pathParams' => [],
                'queryParams' => [],
            ],
            [
                'name' => 'get_flock_incubation',
                'description' => 'Get one incubation batch by ID, including its hatch events.',
                'path' => '/flock-incubations/{flock_incubation}',
                'pathParams' => [['name' => 'flock_incubation', 'description' => 'Flock incubation batch ID']],
                'queryParams' => [],
            ],
            [
                'name' => 'list_hatch_events',
                'description' => 'List the hatch (birth) events recorded for one incubation batch.',
                'path' => '/flock-incubations/{flock_incubation}/hatch-events',
                'pathParams' => [['name' => 'flock_incubation', 'description' => 'Flock incubation batch ID']],
                'queryParams' => [],
            ],
            [
                'name' => 'list_vendor_stock',
                'description' => 'List product stock allocated to vendedores (per-vendedor inventory).',
                'path' => '/vendor-stock',
                'pathParams' => [],
                'queryParams' => [],
            ],
            [
                'name' => 'get_vendor_stock',
                'description' => 'Get one vendor-stock record (a product allocation to a vendedor) by ID.',
                'path' => '/vendor-stock/{vendor_stock}',
                'pathParams' => [['name' => 'vendor_stock', 'description' => 'Vendor stock record ID']],
                'queryParams' => [],
            ],
            [
                'name' => 'list_sales',
                'description' => 'List all sales, each including its exclusion flag if marked as a one-off event.',
                'path' => '/sales',
                'pathParams' => [],
                'queryParams' => [],
            ],
            [
                'name' => 'get_sale',
                'description' => 'Get one sale by ID, including its exclusion flag if any.',
                'path' => '/sales/{sale}',
                'pathParams' => [['name' => 'sale', 'description' => 'Sale ID']],
                'queryParams' => [],
            ],
            [
                'name' => 'list_stock_transfers',
                'description' => 'List all stock transfers between flock/plantel and vendedores.',
                'path' => '/stock-transfers',
                'pathParams' => [],
                'queryParams' => [],
            ],
            [
                'name' => 'get_stock_transfer',
                'description' => 'Get one stock transfer by ID.',
                'path' => '/stock-transfers/{stock_transfer}',
                'pathParams' => [['name' => 'stock_transfer', 'description' => 'Stock transfer ID']],
                'queryParams' => [],
            ],
            [
                'name' => 'list_daily_productions',
                'description' => 'List daily egg production records by species.',
                'path' => '/daily-productions',
                'pathParams' => [],
                'queryParams' => [],
            ],
            [
                'name' => 'get_daily_production',
                'description' => 'Get one daily production record by ID.',
                'path' => '/daily-productions/{daily_production}',
                'pathParams' => [['name' => 'daily_production', 'description' => 'Daily production record ID']],
                'queryParams' => [],
            ],
            [
                'name' => 'list_expenses',
                'description' => 'List all expenses, each including its species override if one was set.',
                'path' => '/expenses',
                'pathParams' => [],
                'queryParams' => [],
            ],
            [
                'name' => 'get_expense',
                'description' => 'Get one expense by ID, including its species override if any.',
                'path' => '/expenses/{expense}',
                'pathParams' => [['name' => 'expense', 'description' => 'Expense ID']],
                'queryParams' => [],
            ],
            [
                'name' => 'list_cash_flows',
                'description' => 'List cash flow entries (income and outflows).',
                'path' => '/cash-flows',
                'pathParams' => [],
                'queryParams' => [],
            ],
            [
                'name' => 'get_cash_flow',
                'description' => 'Get one cash flow entry by ID.',
                'path' => '/cash-flows/{cash_flow}',
                'pathParams' => [['name' => 'cash_flow', 'description' => 'Cash flow entry ID']],
                'queryParams' => [],
            ],
            [
                'name' => 'list_feed_stocks',
                'description' => 'List feed (ração) stock by type, with current bag/kg balances.',
                'path' => '/feed-stocks',
                'pathParams' => [],
                'queryParams' => [],
            ],
            [
                'name' => 'get_feed_stock',
                'description' => 'Get one feed stock type by ID.',
                'path' => '/feed-stocks/{feed_stock}',
                'pathParams' => [['name' => 'feed_stock', 'description' => 'Feed stock type ID']],
                'queryParams' => [],
            ],
            [
                'name' => 'list_feed_open_logs',
                'description' => 'List the history of opened feed bags (read-only log; written only by the open-bag action).',
                'path' => '/feed-open-logs',
                'pathParams' => [],
                'queryParams' => [],
            ],
            [
                'name' => 'list_flock_cleanings',
                'description' => 'List flock/plantel cleaning records.',
                'path' => '/flock-cleanings',
                'pathParams' => [],
                'queryParams' => [],
            ],
            [
                'name' => 'get_flock_cleaning',
                'description' => 'Get one flock/plantel cleaning record by ID.',
                'path' => '/flock-cleanings/{flock_cleaning}',
                'pathParams' => [['name' => 'flock_cleaning', 'description' => 'Flock cleaning record ID']],
                'queryParams' => [],
            ],
            [
                'name' => 'get_business_line_report',
                'description' => 'Get the Business Line Analysis report comparing quail vs. chicken (revenue, cost, profit, '.
                    'margin), built from sales (excluding ones marked as one-off events), expenses (respecting species '.
                    'overrides), flock and products. Optional start/end date range; omitting both covers the full history.',
                'path' => '/business-line-report',
                'pathParams' => [],
                'queryParams' => [
                    ['name' => 'start', 'description' => 'Start of the period, inclusive, as YYYY-MM-DD. Omit for no lower bound.'],
                    ['name' => 'end', 'description' => 'End of the period, inclusive, as YYYY-MM-DD. Must be >= start. Omit for no upper bound.'],
                ],
            ],
        ];
    }
}
```

- [ ] **Step 4: Rodar e confirmar que passa**

Run: `php artisan test tests/Unit/Mcp/ToolDefinitionsTest.php`
Expected: PASS (6 testes)

- [ ] **Step 5: Commit**

```bash
git add app/Mcp/ToolDefinitions.php tests/Unit/Mcp/ToolDefinitionsTest.php
git commit -m "feat: as 29 definições de tool MCP a partir dos endpoints GET do openapi.yaml

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 6: `App\Mcp\McpServerFactory` (monta o `Mcp\Server` a partir das definições)

**Files:**
- Create: `app/Mcp/McpServerFactory.php`
- Test: `tests/Unit/Mcp/McpServerFactoryTest.php`

**Interfaces:**
- Consumes: `App\Mcp\ToolDefinitions::all()` (Task 5), `App\Mcp\ErpApiClient::get()` (Task 4), SDK: `Mcp\Server::builder()`, `Mcp\Capability\Registry`, `Mcp\Schema\ToolAnnotations`, `Mcp\Exception\ToolCallException`.
- Produces: `App\Mcp\McpServerFactory::__construct(ErpApiClient $apiClient)`, `->build(): \Mcp\Server`, `->registry(): \Mcp\Capability\Registry` (só depois de `build()` — usado pelos testes pra inspecionar o que foi registrado; consumido pela Task 7, `McpServeCommand`, só via `build()`).

- [ ] **Step 1: Escrever o teste (falha primeiro)**

Create `tests/Unit/Mcp/McpServerFactoryTest.php`:

```php
<?php

namespace Tests\Unit\Mcp;

use App\Mcp\ErpApiClient;
use App\Mcp\McpServerFactory;
use App\Mcp\ToolDefinitions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Mcp\Exception\ToolCallException;
use Tests\TestCase;

class McpServerFactoryTest extends TestCase
{
    public function test_registers_exactly_29_tools_with_expected_names(): void
    {
        $factory = new McpServerFactory(new ErpApiClient());
        $factory->build();

        $tools = $factory->registry()->getTools();

        $this->assertCount(29, $tools);
        $this->assertSame(
            array_map(fn (array $def) => $def['name'], ToolDefinitions::all()),
            array_keys((array) $tools->references),
        );
    }

    public function test_get_sale_tool_requires_integer_sale_id(): void
    {
        $factory = new McpServerFactory(new ErpApiClient());
        $factory->build();

        $tool = $factory->registry()->getTool('get_sale')->tool;

        $this->assertSame(['sale'], $tool->inputSchema['required']);
        $this->assertSame('integer', $tool->inputSchema['properties']['sale']['type']);
    }

    public function test_get_business_line_report_tool_has_optional_start_end(): void
    {
        $factory = new McpServerFactory(new ErpApiClient());
        $factory->build();

        $tool = $factory->registry()->getTool('get_business_line_report')->tool;

        $this->assertSame([], $tool->inputSchema['required']);
        $this->assertArrayHasKey('start', $tool->inputSchema['properties']);
        $this->assertArrayHasKey('end', $tool->inputSchema['properties']);
    }

    public function test_tool_annotations_mark_read_only(): void
    {
        $factory = new McpServerFactory(new ErpApiClient());
        $factory->build();

        $annotations = $factory->registry()->getTool('list_sales')->tool->annotations;

        $this->assertTrue($annotations->readOnlyHint);
        $this->assertFalse($annotations->destructiveHint);
        $this->assertTrue($annotations->idempotentHint);
        $this->assertFalse($annotations->openWorldHint);
    }

    public function test_tool_handler_calls_the_api_client_and_returns_its_result(): void
    {
        Config::set('mcp.token', 'abc123');
        Config::set('mcp.base_url', 'https://mcp-test.example/api');
        Http::fake(['*' => Http::response(['id' => 42, 'total' => 10.5], 200)]);

        $factory = new McpServerFactory(new ErpApiClient());
        $factory->build();

        $handler = $factory->registry()->getTool('get_sale')->handler;
        $result = $handler(['sale' => 42]);

        $this->assertSame(['id' => 42, 'total' => 10.5], $result);
        Http::assertSent(fn ($request) => $request->url() === 'https://mcp-test.example/api/sales/42');
    }

    public function test_tool_handler_wraps_api_errors_as_tool_call_exception(): void
    {
        Config::set('mcp.token', 'abc123');
        Config::set('mcp.base_url', 'https://mcp-test.example/api');
        Http::fake(['*' => Http::response(['message' => 'No query results for model.'], 404)]);

        $factory = new McpServerFactory(new ErpApiClient());
        $factory->build();

        $handler = $factory->registry()->getTool('get_sale')->handler;

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessageMatches('/No query results for model\./');

        $handler(['sale' => 999]);
    }
}
```

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `php artisan test tests/Unit/Mcp/McpServerFactoryTest.php`
Expected: FAIL — `Class "App\Mcp\McpServerFactory" not found`

- [ ] **Step 3: Implementar `App\Mcp\McpServerFactory`**

Create `app/Mcp/McpServerFactory.php`:

```php
<?php

namespace App\Mcp;

use Mcp\Capability\Registry;
use Mcp\Exception\ToolCallException;
use Mcp\Schema\ToolAnnotations;
use Mcp\Server;

/**
 * Monta o `Mcp\Server` a partir de `ToolDefinitions` — um `addTool()` por
 * entrada, registrado num `Registry` próprio (via `setRegistry()`) pra ficar
 * inspecionável nos testes depois de `build()`.
 */
final class McpServerFactory
{
    private ?Registry $registry = null;

    public function __construct(private readonly ErpApiClient $apiClient)
    {
    }

    public function build(): Server
    {
        $registry = new Registry();
        $this->registry = $registry;

        $builder = Server::builder()
            ->setServerInfo('erp-mcp-php', '1.0.0')
            ->setRegistry($registry);

        foreach (ToolDefinitions::all() as $def) {
            $builder->addTool(
                handler: fn (array $args) => $this->callTool($def, $args),
                name: $def['name'],
                description: $def['description'],
                inputSchema: $this->buildInputSchema($def),
                annotations: new ToolAnnotations(
                    readOnlyHint: true,
                    destructiveHint: false,
                    idempotentHint: true,
                    openWorldHint: false,
                ),
            );
        }

        return $builder->build();
    }

    /** Só disponível depois de `build()` — usado pra inspecionar o que foi registrado. */
    public function registry(): Registry
    {
        return $this->registry ?? throw new \LogicException('Chame build() antes de registry().');
    }

    /**
     * @param array{name: string, description: string, path: string, pathParams: array, queryParams: array} $def
     * @param array<string, mixed> $args
     */
    private function callTool(array $def, array $args): array
    {
        $pathParams = [];
        foreach ($def['pathParams'] as $param) {
            $pathParams[$param['name']] = $args[$param['name']] ?? null;
        }

        $query = [];
        foreach ($def['queryParams'] as $param) {
            $query[$param['name']] = $args[$param['name']] ?? null;
        }

        try {
            return $this->apiClient->get($def['path'], $pathParams, $query);
        } catch (\Throwable $e) {
            throw new ToolCallException($e->getMessage(), previous: $e);
        }
    }

    /**
     * @param array{pathParams: array, queryParams: array} $def
     * @return array<string, mixed>
     */
    private function buildInputSchema(array $def): array
    {
        $properties = [];
        $required = [];

        foreach ($def['pathParams'] as $param) {
            $properties[$param['name']] = [
                'type' => 'integer',
                'minimum' => 1,
                'description' => $param['description'],
            ];
            $required[] = $param['name'];
        }

        foreach ($def['queryParams'] as $param) {
            $properties[$param['name']] = [
                'type' => 'string',
                'pattern' => '^\d{4}-\d{2}-\d{2}$',
                'description' => $param['description'],
            ];
        }

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
            'additionalProperties' => false,
        ];
    }
}
```

- [ ] **Step 4: Rodar e confirmar que passa**

Run: `php artisan test tests/Unit/Mcp/McpServerFactoryTest.php`
Expected: PASS (6 testes)

- [ ] **Step 5: Rodar a suíte inteira de Mcp**

Run: `php artisan test tests/Unit/Mcp`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Mcp/McpServerFactory.php tests/Unit/Mcp/McpServerFactoryTest.php
git commit -m "feat: McpServerFactory registra as 29 tools no Mcp\\Server

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 7: Comando `php artisan mcp:serve` (transporte STDIO)

**Files:**
- Create: `app/Console/Commands/McpServeCommand.php`
- Test: `tests/Unit/Mcp/McpServeCommandTest.php`

**Interfaces:**
- Consumes: `App\Mcp\McpServerFactory` (Task 6, resolvido via container — Laravel autowira, sem `ServiceProvider` novo porque nem `ErpApiClient` nem `McpServerFactory` têm dependências não-concretas no construtor).
- Produces: comando artisan `mcp:serve`, sem interface consumida por outra task (é o ponto de entrada final).

- [ ] **Step 1: Escrever o teste (falha primeiro)**

Este teste cobre só o aviso de token ausente indo pra STDERR e o registro do comando — **não** chama `handle()` de verdade (isso bloquearia esperando STDIN). A cobertura de "roda o servidor" já está na Task 6 (`McpServerFactory::build()` é testado isoladamente).

Create `tests/Unit/Mcp/McpServeCommandTest.php`:

```php
<?php

namespace Tests\Unit\Mcp;

use App\Console\Commands\McpServeCommand;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class McpServeCommandTest extends TestCase
{
    public function test_command_is_registered(): void
    {
        $this->assertArrayHasKey('mcp:serve', Artisan::all());
    }

    public function test_command_class_only_writes_warnings_to_stderr_helper(): void
    {
        // Garante que o comando não usa $this->info()/$this->line() (que vão pra STDOUT) —
        // só fwrite(STDERR, ...) e o retorno de Server::run(). Regressão-guard textual:
        // se alguém trocar por Artisan output, este teste falha.
        $source = file_get_contents((new \ReflectionClass(McpServeCommand::class))->getFileName());

        $this->assertStringNotContainsString('$this->info(', $source);
        $this->assertStringNotContainsString('$this->line(', $source);
        $this->assertStringContainsString('STDERR', $source);
    }
}
```

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `php artisan test tests/Unit/Mcp/McpServeCommandTest.php`
Expected: FAIL — `Class "App\Console\Commands\McpServeCommand" not found`

- [ ] **Step 3: Implementar o comando**

Create `app/Console/Commands/McpServeCommand.php`:

```php
<?php

namespace App\Console\Commands;

use App\Mcp\McpServerFactory;
use Illuminate\Console\Command;
use Mcp\Server\Transport\StdioTransport;

/**
 * Sobe o servidor MCP (PHP) em modo STDIO, pra um cliente MCP (Claude Code/
 * Desktop) rodar como subprocesso. Ver docs/MCP.md.
 *
 * IMPORTANTE: STDOUT é o canal do protocolo JSON-RPC — nunca escrever nada
 * nele fora do que o próprio SDK escreve. Avisos/logs sempre em STDERR.
 */
class McpServeCommand extends Command
{
    protected $signature = 'mcp:serve';

    protected $description = 'Sobe o servidor MCP de leitura (STDIO) que expõe a API do MiniERP como tools';

    public function __construct(private readonly McpServerFactory $factory)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (empty(config('mcp.token'))) {
            fwrite(
                STDERR,
                "[erp-mcp-php] Aviso: ERP_API_TOKEN não está configurado. Chamadas de tool vão falhar até um ".
                "token Sanctum válido ser configurado — veja docs/MCP.md \"Como obter um token\".\n",
            );
        }

        fwrite(STDERR, '[erp-mcp-php] Servidor MCP read-only rodando (API base: '.config('mcp.base_url').")\n");

        $exitCode = $this->factory->build()->run(new StdioTransport());

        return \is_int($exitCode) ? $exitCode : self::SUCCESS;
    }
}
```

- [ ] **Step 4: Rodar e confirmar que passa**

Run: `php artisan test tests/Unit/Mcp/McpServeCommandTest.php`
Expected: PASS (2 testes)

- [ ] **Step 5: Fumaça manual — confirmar que o comando sobe e responde ao handshake MCP**

Run:
```bash
echo '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"smoke-test","version":"0.0.0"}}}' | php artisan mcp:serve
```
Expected: uma linha de JSON no stdout com `"result"` contendo `serverInfo.name = "erp-mcp-php"` (o aviso `[erp-mcp-php] Servidor MCP...` aparece em stderr, misturado no terminal, mas não no JSON). `ERP_API_TOKEN`/`ERP_API_BASE_URL` do `.env` local valem aqui — não precisa estar setado pro handshake funcionar, só pras chamadas de tool.

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/McpServeCommand.php tests/Unit/Mcp/McpServeCommandTest.php
git commit -m "feat: comando 'php artisan mcp:serve' (transporte STDIO)

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 8: Documentação (`docs/MCP.md` + link do `README.md`)

**Files:**
- Create: `docs/MCP.md`
- Modify: `README.md`

**Interfaces:** nenhuma (documentação).

- [ ] **Step 1: Criar `docs/MCP.md`**

Create `docs/MCP.md`:

````markdown
# Servidor MCP (PHP)

Servidor [MCP](https://modelcontextprotocol.io) de leitura, em PHP, vivendo dentro deste próprio backend (`ERP-Backend`). Expõe a API do MiniERP como tools tipadas pra um assistente de IA.

## Por que existe

Já existe um servidor MCP equivalente em Node/TypeScript, `../ERP-MCP` — feito antes de existir um SDK oficial de MCP em PHP. Agora que existe (`mcp/sdk`, mantido por Symfony + PHP Foundation), faz sentido ter a versão nativa no mesmo toolchain do backend (PHP/Laravel), pelo mesmo motivo original do Node: acesso rápido e seguro de leitura a dados de produção, sem escrever `tinker` na mão pra cada pergunta, e sem depender de um segundo toolchain (Node) só pra isso.

**Ver [nota de segurança](#dois-servidores-mcp-pra-este-backend) no fim — leia antes de mexer em qualquer um dos dois.**

## Instalar

```bash
composer install
```

`mcp/sdk` já é uma dependência do projeto (`composer.json`) — nada a mais pra instalar.

## Configurar

Duas env vars, no `.env` deste projeto (ou exportadas no shell/config do cliente MCP):

| Variável | Obrigatória | Default | Significado |
|---|---|---|---|
| `ERP_API_BASE_URL` | não | `APP_URL` local + `/api` | Base da API do MiniERP, sem barra final |
| `ERP_API_TOKEN` | sim | — | Token de acesso Sanctum, enviado como `Authorization: Bearer <token>` |

Pra usar contra a **produção**:

```env
ERP_API_BASE_URL=https://laravel-production-4c67.up.railway.app/api
ERP_API_TOKEN=<obtido via POST https://laravel-production-4c67.up.railway.app/api/login>
```

### Como obter um token

Não existe hoje comando artisan nem fluxo admin que emita um token read-only dedicado — a única emissão de token é via login. Contra produção:

```bash
curl -X POST https://laravel-production-4c67.up.railway.app/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"voce@example.com","password":"sua-senha"}'
```

A resposta traz um `access_token` (ability `access`, **expira em 2h**) e um `refresh_token` (ability `refresh`, expira em 30 dias, roda a cada uso). Copie o valor de `access_token` pra `ERP_API_TOKEN`.

Ressalvas honestas (mesmas do README do Node):

- O token expira em 2h — espere ter que logar de novo periodicamente; este servidor não tem fluxo de refresh automático.
- **Não existe token com escopo read-only na API.** A ability `access` cobre toda rota que o usuário autenticado alcança, GET e escrita. Este servidor nunca emite escrita independente do token — mas o token em si não é restrito a leitura na camada da API. O limite read-only é garantido só na camada MCP (`ErpApiClient` recusa qualquer verbo != GET), não na camada API/token.
- Se um token com escopo restrito de verdade for necessário no futuro, isso é mudança de backend (ex.: comando artisan que emite uma ability `readonly` nova, checada por middleware de rota novo) — não algo pra simular aqui.

## Registrar no Claude Code / Claude Desktop

Aponta pro comando artisan, com as env vars setadas (exemplo já usando a base de produção):

**Claude Code:**

```bash
claude mcp add erp-mcp-php -- php /caminho/absoluto/para/ERP-Backend/artisan mcp:serve
```

Depois, defina as env vars nesse servidor MCP (`claude mcp add` aceita `--env`, ex. `-e ERP_API_TOKEN=... -e ERP_API_BASE_URL=https://laravel-production-4c67.up.railway.app/api`), ou exporte-as no shell que o Claude Code usa.

**Config JSON cru de cliente MCP:**

```json
{
  "mcpServers": {
    "erp-mcp-php": {
      "command": "php",
      "args": ["/caminho/absoluto/para/ERP-Backend/artisan", "mcp:serve"],
      "env": {
        "ERP_API_BASE_URL": "https://laravel-production-4c67.up.railway.app/api",
        "ERP_API_TOKEN": "1|seu-token-de-acesso-aqui"
      }
    }
  }
}
```

## Tools disponíveis

Uma tool por endpoint `GET` de `docs/openapi.yaml` — mesmo escopo do servidor Node:

| Tool | Descrição |
|---|---|
| `get_current_user` | Get the user data for the currently authenticated API token. |
| `list_users` | List all admin-panel users (not paginated). Excludes the public self-registration flow. |
| `list_products` | List all products sold (eggs, packaging, etc.). |
| `get_product` | Get one product by ID. |
| `list_vendedores` | List all vendedores (resellers/salespeople) registered in the system. |
| `get_vendedor` | Get one vendedor (reseller/salesperson) by ID. |
| `list_flock` | List all flock/plantel batches (quail or chicken batches in production). |
| `get_flock` | Get one flock/plantel batch by ID. |
| `list_flock_incubations` | List all incubation batches, each including its hatch events. |
| `get_flock_incubation` | Get one incubation batch by ID, including its hatch events. |
| `list_hatch_events` | List the hatch (birth) events recorded for one incubation batch. |
| `list_vendor_stock` | List product stock allocated to vendedores (per-vendedor inventory). |
| `get_vendor_stock` | Get one vendor-stock record (a product allocation to a vendedor) by ID. |
| `list_sales` | List all sales, each including its exclusion flag if marked as a one-off event. |
| `get_sale` | Get one sale by ID, including its exclusion flag if any. |
| `list_stock_transfers` | List all stock transfers between flock/plantel and vendedores. |
| `get_stock_transfer` | Get one stock transfer by ID. |
| `list_daily_productions` | List daily egg production records by species. |
| `get_daily_production` | Get one daily production record by ID. |
| `list_expenses` | List all expenses, each including its species override if one was set. |
| `get_expense` | Get one expense by ID, including its species override if any. |
| `list_cash_flows` | List cash flow entries (income and outflows). |
| `get_cash_flow` | Get one cash flow entry by ID. |
| `list_feed_stocks` | List feed (ração) stock by type, with current bag/kg balances. |
| `get_feed_stock` | Get one feed stock type by ID. |
| `list_feed_open_logs` | List the history of opened feed bags (read-only log; written only by the open-bag action). |
| `list_flock_cleanings` | List flock/plantel cleaning records. |
| `get_flock_cleaning` | Get one flock/plantel cleaning record by ID. |
| `get_business_line_report` | Business Line Analysis report comparing quail vs. chicken (revenue, cost, profit, margin), with optional `start`/`end` date filters. |

Fonte de verdade: `app/Mcp/ToolDefinitions.php` — essa tabela, não o contrário.

## Segurança

Read-only por construção:

- `App\Mcp\ErpApiClient` só expõe `get()`. O `request()` interno recusa qualquer verbo != GET, mesmo que um refactor futuro tente passar outro.
- Nenhuma tool de escrita é registrada — só endpoints GET viram tool.
- Se um dia quiser tools de escrita, isso deve ser uma adição deliberada e cuidadosamente escopada (confirmação explícita, capability flag), nunca só relaxar a guarda do client.

## Dois servidores MCP pra este backend

Hoje existem **dois** servidores MCP equivalentes pra este backend:

- **Node** (`../ERP-MCP`) — feito primeiro, antes de existir um SDK oficial de MCP em PHP.
- **PHP** (aqui, `app/Mcp/` + `php artisan mcp:serve`) — adicionado depois, por conveniência de ter um único toolchain (PHP) pra manter.

Os dois devem continuar em paridade de escopo (mesmas tools, mesmo comportamento read-only) até decisão em contrário. **Não adicione tools de escrita casualmente em nenhum dos dois** — nem "só uma tool de escrita simples" — sem revisar deliberadamente as implicações de segurança (confirmação explícita, capability flag, revisão separada).

## Desenvolvimento

```bash
php artisan test tests/Unit/Mcp   # só os testes deste servidor
composer test                     # suíte inteira do backend
php artisan mcp:serve             # roda localmente (precisa de ERP_API_TOKEN válido pras tools funcionarem)
```
````

- [ ] **Step 2: Linkar do `README.md`**

Read `README.md` first (to place the link in a sensible spot). Then add, right after the "## Estrutura de rotas" section (or the closest equivalent structural section), a short new section:

```markdown
## Servidor MCP (PHP)

Servidor [MCP](https://modelcontextprotocol.io) de leitura, em PHP, vivendo dentro deste repo (`app/Mcp/` + `php artisan mcp:serve`) — expõe a API como tools tipadas pra um assistente de IA. Ver [`docs/MCP.md`](docs/MCP.md) (instalação, configuração, registro no Claude Code/Desktop, tabela de tools). Existe também um servidor Node equivalente em `../ERP-MCP` — ver a nota de "dois servidores" em `docs/MCP.md`.
```

- [ ] **Step 3: Commit**

```bash
git add docs/MCP.md README.md
git commit -m "docs: como usar o servidor MCP em PHP (docs/MCP.md + link do README)

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 9: Verificação final (suíte completa + Pint)

**Files:** nenhum novo — só verificação.

- [ ] **Step 1: Rodar a suíte inteira**

Run: `composer test`
Expected: todos os testes passam (os pré-existentes + os novos de `tests/Unit/Mcp/`).

- [ ] **Step 2: Rodar o Pint nos arquivos novos**

Run: `vendor/bin/pint app/Mcp app/Console/Commands/McpServeCommand.php tests/Unit/Mcp --test`

Expected: sem violações. Se houver, rodar sem `--test` pra auto-corrigir, revisar o diff, e rodar `composer test` de novo.

- [ ] **Step 3: `composer install` limpo (confirma que o lockfile está consistente)**

Run: `rm -rf vendor && composer install`
Expected: instala sem erro, sem prompts de conflito de versão.

- [ ] **Step 4: Confirmar contagem exata de tools de ponta a ponta**

Run: `php artisan test --filter=test_has_exactly_29_tools`
Expected: PASS — 1 teste, 29 confirmado tanto em `ToolDefinitions` quanto (Task 6) no `Registry` de verdade.

- [ ] **Step 5: Commit final (se o Pint tiver mudado algo)**

```bash
git add -A
git commit -m "chore: formatação Pint nos arquivos do servidor MCP em PHP

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

(Pular este commit se o Step 2 não alterou nada.)

---

## Fora deste plano (decisões operacionais, não técnicas)

- **Push**: não faz parte deste plano. Depois de todos os commits acima, confirmar com o usuário a política de push atual do repo antes de dar `git push`.
