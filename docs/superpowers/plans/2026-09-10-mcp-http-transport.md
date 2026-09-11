# Transporte HTTP pro Servidor MCP em PHP Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Adicionar transporte HTTP (`POST /api/mcp`) ao servidor MCP em PHP já existente, reaproveitando o deploy Railway atual, sem alterar o transporte STDIO (`php artisan mcp:serve`) já em produção.

**Architecture:** `Mcp\Server\Transport\StatelessHttpTransport` do SDK oficial (nenhum protocolo reimplementado na mão), alimentado por `App\Mcp\McpServerFactory::buildStatelessProtocol()` (método novo, reusa a mesma configuração de tools de `build()`). Ponte Laravel↔PSR-7 via `symfony/psr-http-message-bridge` (nova dependência; as factories PSR-17 vêm de graça do `guzzlehttp/psr7` já instalado). Autenticação dedicada por token de env (não Sanctum), rate limit via `RateLimiter`/`throttle` do Laravel.

**Tech Stack:** Laravel 13 / PHP 8.4, `mcp/sdk` (já instalado), `symfony/psr-http-message-bridge` (novo), PHPUnit (`php artisan test`).

## Global Constraints

- `php artisan mcp:serve` (STDIO) **não muda de comportamento** — mesmo `App\Mcp\McpServerFactory::build(): \Mcp\Server`, mesma assinatura, mesmos testes existentes (`McpServerFactoryTest`, `McpServeCommandTest`, `McpProtocolRoundTripTest`) continuam passando sem alteração.
- Rota final: `POST /api/mcp` (dentro do prefixo `/api` automático de `routes/api.php`).
- Autenticação: header `Authorization: Bearer <MCP_HTTP_TOKEN>`, comparado via `hash_equals` a `config('mcp.http_token')` (env `MCP_HTTP_TOKEN`). 401 se ausente, errado, **ou** se `MCP_HTTP_TOKEN` não estiver configurado (nunca abre a rota por omissão).
- Rate limit: 30 requisições/minuto por IP, via limiter nomeado `mcp` (`RateLimiter::for('mcp', ...)` em `AppServiceProvider::boot()`, seguindo o padrão já usado ali pros limiters `login`/`password-reset`), aplicado à rota como `throttle:mcp`.
- Nenhuma tool de escrita, nenhuma mudança em `../ERP-MCP` (sibling Node project, fora deste repo).
- Testes do transporte HTTP em `tests/Feature/` (não `tests/Unit/Mcp/`) — batem na stack HTTP real via `postJson`/`withHeaders`, não chamam lógica direto (é exatamente o que faltou da vez passada e deixou passar um bug Critical).
- Documentação (`docs/MCP.md` + `README.md`) deve deixar claro que agora há DUAS formas de acesso (STDIO local, HTTP em produção), com exemplo de config JSON apontando pra `https://laravel-production-4c67.up.railway.app/api/mcp`, como gerar/setar `MCP_HTTP_TOKEN` no Railway, e aviso de segurança (endpoint público, token é segredo, rotacionar se vazar).
- Commit numa branch, **sem merge direto em `main`** — abrir/estender PR.

---

### Task 1: Instalar a ponte Laravel↔PSR-7

**Files:**
- Modify: `composer.json`, `composer.lock`

**Interfaces:**
- Produces: `Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory` e `Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory` disponíveis via autoload — consumidos pela Task 5 (`McpHttpController`).

- [ ] **Step 1: Instalar o pacote**

Run: `composer require symfony/psr-http-message-bridge`

Expected: resolve sem conflito (o projeto já tem `symfony/http-foundation` v8.x via Laravel 13, e `guzzlehttp/psr7` já instalado transitivamente satisfaz a implementação PSR-17 que a ponte precisa — não é necessário instalar `nyholm/psr7` nem qualquer outro pacote PSR-7).

- [ ] **Step 2: Confirmar autoload das duas classes**

Run: `php -r "require 'vendor/autoload.php'; var_dump(class_exists(\Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory::class), class_exists(\Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory::class));"`

Expected: `bool(true) bool(true)`

- [ ] **Step 3: Confirmar que a suíte de testes existente continua passando**

Run: `composer test`

Expected: 180 testes passando (baseline atual), 0 falha — instalar uma dependência não deve quebrar nada.

- [ ] **Step 4: Commit**

```bash
git add composer.json composer.lock
git commit -m "chore: instala symfony/psr-http-message-bridge (ponte Laravel <-> PSR-7 pro transporte HTTP do MCP)

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 2: Config do token HTTP (`MCP_HTTP_TOKEN`)

**Files:**
- Modify: `config/mcp.php`
- Modify: `phpunit.xml`
- Modify: `.env.example`

**Interfaces:**
- Consumes: `App\Mcp\McpConfig::resolveToken()` (já existe, mesma função reusada — trim + blank-como-null).
- Produces: `config('mcp.http_token')` — consumido pela Task 4 (`AuthenticateMcpHttp` middleware).

Sem teste PHPUnit dedicado nesta task — `McpConfig::resolveToken()` já é testado em `McpConfigTest`; o novo uso de `config('mcp.http_token')` é exercitado pelos testes de middleware/feature das Tasks 4 e 6.

- [ ] **Step 1: Adicionar a chave em `config/mcp.php`**

In `config/mcp.php`, add `'http_token'` to the returned array (keep `base_url`/`token` as they are):

```php
return [
    'base_url' => McpConfig::resolveBaseUrl(env('ERP_API_BASE_URL'), env('APP_URL')),
    'token' => McpConfig::resolveToken(env('ERP_API_TOKEN')),

    // MCP_HTTP_TOKEN: token dedicado da rota HTTP (POST /api/mcp) — NÃO é o
    // Sanctum de usuário nem o ERP_API_TOKEN acima (esse chama a API do
    // MiniERP; este autentica quem pode falar com o servidor MCP). Ver
    // docs/MCP.md "Transporte HTTP" pra como gerar e setar no Railway.
    'http_token' => McpConfig::resolveToken(env('MCP_HTTP_TOKEN')),
];
```

Also update the file's top comment block to mention `MCP_HTTP_TOKEN` alongside `ERP_API_BASE_URL`/`ERP_API_TOKEN`.

- [ ] **Step 2: Adicionar env fixo de teste no `phpunit.xml`**

In `phpunit.xml`, inside the existing `<php>` block (next to `ERP_API_BASE_URL`/`ERP_API_TOKEN`), add:

```xml
        <env name="MCP_HTTP_TOKEN" value="test-mcp-http-token-456"/>
```

- [ ] **Step 3: Documentar em `.env.example`**

Add to `.env.example` (near any existing MCP-related lines, or at the end):

```env
# Servidor MCP (PHP) — ver docs/MCP.md
# ERP_API_BASE_URL=
# ERP_API_TOKEN=
# MCP_HTTP_TOKEN=
```

If `.env.example` already has `ERP_API_BASE_URL`/`ERP_API_TOKEN` lines from earlier work, just add `MCP_HTTP_TOKEN` alongside them instead of duplicating the block.

- [ ] **Step 4: Rodar a suíte de Mcp e confirmar que nada quebrou**

Run: `php artisan test tests/Unit/Mcp`

Expected: PASS (sem regressão — esta task não muda comportamento de nada testado ainda).

- [ ] **Step 5: Commit**

```bash
git add config/mcp.php phpunit.xml .env.example
git commit -m "feat: config do MCP_HTTP_TOKEN (rota HTTP do MCP)

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 3: `McpServerFactory::buildStatelessProtocol()` (reusa a config de tools pro transporte HTTP)

**Files:**
- Modify: `app/Mcp/McpServerFactory.php`
- Test: `tests/Unit/Mcp/McpServerFactoryTest.php`

**Interfaces:**
- Consumes: `Mcp\Server\Builder` (já usado), `Mcp\Server\Stateless\StatelessProtocol` (novo, do SDK).
- Produces: `App\Mcp\McpServerFactory::buildStatelessProtocol(): \Mcp\Server\Stateless\StatelessProtocol` — consumido pela Task 5 (`McpHttpController`). `build(): \Mcp\Server` mantém a MESMA assinatura e comportamento — nenhum teste existente que o cobre deve precisar mudar.

- [ ] **Step 1: Ler o `McpServerFactory.php` atual**

Read `app/Mcp/McpServerFactory.php` primeiro — o refactor abaixo extrai o loop de registro de tools (hoje inline dentro de `build()`) pra um método privado `registerTools(Builder $builder): void`, reusado por `build()` e pelo novo `buildStatelessProtocol()`. Nada na lógica de `buildInputSchema()`/`makeLogger()`/`registry()` muda.

- [ ] **Step 2: Escrever o teste (falha primeiro)**

Add to `tests/Unit/Mcp/McpServerFactoryTest.php` (keep every existing test method in the file untouched — this only adds new ones):

```php
    public function test_build_stateless_protocol_registers_the_same_29_tools(): void
    {
        $factory = new McpServerFactory(new ErpApiClient());
        $protocol = $factory->buildStatelessProtocol();

        $this->assertInstanceOf(\Mcp\Server\Stateless\StatelessProtocol::class, $protocol);

        $tools = $factory->registry()->getTools();
        $this->assertCount(29, $tools);
    }

    public function test_build_still_returns_a_server_with_29_tools_after_refactor(): void
    {
        // Regression guard for the registerTools() extraction: build() must
        // keep behaving exactly as before.
        $factory = new McpServerFactory(new ErpApiClient());
        $factory->build();

        $this->assertCount(29, $factory->registry()->getTools());
    }
```

Add `use Mcp\Server\Stateless\StatelessProtocol;` to the test file's imports if not already implied by the fully-qualified reference above (either form is fine, keep consistent with the rest of the file's import style).

- [ ] **Step 3: Rodar e confirmar que falha**

Run: `php artisan test tests/Unit/Mcp/McpServerFactoryTest.php`
Expected: FAIL — `Call to undefined method App\Mcp\McpServerFactory::buildStatelessProtocol()`

- [ ] **Step 4: Refatorar `app/Mcp/McpServerFactory.php`**

Replace the file's content with (only `build()`'s body changes shape — extracted into `registerTools()` — and two new pieces are added: the `buildStatelessProtocol()` method and its imports; everything else — `makeLogger()`, `registry()`, `buildInputSchema()` — stays byte-for-byte the same as today):

```php
<?php

namespace App\Mcp;

use Mcp\Capability\Registry;
use Mcp\Schema\Tool;
use Mcp\Schema\ToolAnnotations;
use Mcp\Server;
use Mcp\Server\Builder;
use Mcp\Server\Stateless\StatelessProtocol;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

/**
 * Monta o `Mcp\Server` (STDIO) ou o `Mcp\Server\Stateless\StatelessProtocol`
 * (HTTP) a partir de `ToolDefinitions` — um par `Mcp\Schema\Tool` +
 * `App\Mcp\ToolHandler` por entrada, registrado via `Builder::add()` num
 * `Registry` próprio (via `setRegistry()`) pra ficar inspecionável nos testes.
 *
 * Os dois transportes (`php artisan mcp:serve` via `build()`, `POST /api/mcp`
 * via `buildStatelessProtocol()`) reusam a MESMA configuração de tools —
 * `registerTools()` é a única fonte de verdade de como as 29 tools viram
 * elementos do SDK. `build()` continua com o mesmo comportamento de antes.
 *
 * `Builder::add()` e não `addTool()`: ver o docblock de `ToolHandler` — o
 * `addTool()` faz o `ReferenceHandler` mapear os argumentos JSON-RPC nos
 * parâmetros do handler por nome (reflection), então uma closure
 * `fn (array $args)` morre com -32603 em toda chamada.
 */
final class McpServerFactory
{
    private ?Registry $registry = null;

    public function __construct(private readonly ErpApiClient $apiClient) {}

    public function build(): Server
    {
        $builder = $this->newBuilder();

        return $builder->build();
    }

    /** Contraparte HTTP de `build()` — mesma config de tools, protocolo stateless (SEP-2575). */
    public function buildStatelessProtocol(): StatelessProtocol
    {
        $builder = $this->newBuilder();

        return $builder->buildStateless();
    }

    private function newBuilder(): Builder
    {
        $registry = new Registry;
        $this->registry = $registry;

        $builder = Server::builder()
            ->setServerInfo('erp-mcp-php', '1.0.0')
            ->setLogger($this->makeLogger())
            ->setRegistry($registry);

        $this->registerTools($builder);

        return $builder;
    }

    private function registerTools(Builder $builder): void
    {
        foreach (ToolDefinitions::all() as $def) {
            $builder->add(
                new Tool(
                    name: $def['name'],
                    title: null,
                    inputSchema: $this->buildInputSchema($def),
                    description: $def['description'],
                    annotations: new ToolAnnotations(
                        readOnlyHint: true,
                        destructiveHint: false,
                        idempotentHint: true,
                        openWorldHint: false,
                    ),
                ),
                new ToolHandler($def, $this->apiClient),
            );
        }
    }

    /**
     * Logs do SDK vão pra STDERR — STDOUT é o canal do protocolo JSON-RPC
     * (mesma restrição documentada em `McpServeCommand`). Warning pra cima só,
     * pra não afogar o stderr do cliente MCP com o debug de cada tools/call.
     */
    private function makeLogger(): LoggerInterface
    {
        return new Logger('erp-mcp-php', [new StreamHandler('php://stderr', Level::Warning)]);
    }

    /** Só disponível depois de `build()`/`buildStatelessProtocol()` — usado pra inspecionar o que foi registrado. */
    public function registry(): Registry
    {
        return $this->registry ?? throw new \LogicException('Chame build() ou buildStatelessProtocol() antes de registry().');
    }

    /**
     * @param  array{pathParams: array, queryParams: array}  $def
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

- [ ] **Step 5: Rodar e confirmar que passa**

Run: `php artisan test tests/Unit/Mcp/McpServerFactoryTest.php`
Expected: PASS (todos os testes existentes do arquivo + os 2 novos)

- [ ] **Step 6: Rodar a suíte inteira de Mcp — confirma que o refactor não quebrou nada do STDIO**

Run: `php artisan test tests/Unit/Mcp`
Expected: PASS — em particular `McpServeCommandTest` e `McpProtocolRoundTripTest` (que exercitam `build()` de ponta a ponta) continuam verdes sem qualquer alteração no próprio arquivo de teste.

- [ ] **Step 7: Fumaça manual — confirmar que `mcp:serve` (STDIO) continua respondendo ao handshake, exatamente como antes do refactor**

Run:
```bash
echo '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"smoke-test","version":"0.0.0"}}}' | php artisan mcp:serve
```
Expected: mesma resposta de sempre — JSON no stdout com `serverInfo.name = "erp-mcp-php"`. Esta é a confirmação exigida pelo usuário de que o STDIO continua 100% intocado depois do refactor de `McpServerFactory`.

- [ ] **Step 8: Commit**

```bash
git add app/Mcp/McpServerFactory.php tests/Unit/Mcp/McpServerFactoryTest.php
git commit -m "feat: McpServerFactory::buildStatelessProtocol() (reusa a config de tools pro transporte HTTP)

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 4: Middleware de autenticação da rota HTTP (`mcp.http-token`)

**Files:**
- Create: `app/Http/Middleware/AuthenticateMcpHttp.php`
- Modify: `bootstrap/app.php`
- Test: `tests/Unit/Http/Middleware/AuthenticateMcpHttpTest.php`

**Interfaces:**
- Consumes: `config('mcp.http_token')` (Task 2).
- Produces: middleware alias `mcp.http-token` — consumido pela Task 5 (rota).

- [ ] **Step 1: Escrever o teste (falha primeiro)**

Create `tests/Unit/Http/Middleware/AuthenticateMcpHttpTest.php`:

```php
<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\AuthenticateMcpHttp;
use Illuminate\Http\Request;
use Tests\TestCase;

class AuthenticateMcpHttpTest extends TestCase
{
    public function test_rejects_request_with_no_authorization_header(): void
    {
        config(['mcp.http_token' => 'segredo-correto']);

        $request = Request::create('/api/mcp', 'POST');
        $middleware = new AuthenticateMcpHttp();

        $response = $middleware->handle($request, fn () => $this->fail('next() não deveria rodar'));

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_rejects_request_with_wrong_token(): void
    {
        config(['mcp.http_token' => 'segredo-correto']);

        $request = Request::create('/api/mcp', 'POST');
        $request->headers->set('Authorization', 'Bearer token-errado');
        $middleware = new AuthenticateMcpHttp();

        $response = $middleware->handle($request, fn () => $this->fail('next() não deveria rodar'));

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_rejects_request_when_token_not_configured(): void
    {
        config(['mcp.http_token' => null]);

        $request = Request::create('/api/mcp', 'POST');
        $request->headers->set('Authorization', 'Bearer qualquer-coisa');
        $middleware = new AuthenticateMcpHttp();

        $response = $middleware->handle($request, fn () => $this->fail('next() não deveria rodar'));

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_allows_request_with_correct_token(): void
    {
        config(['mcp.http_token' => 'segredo-correto']);

        $request = Request::create('/api/mcp', 'POST');
        $request->headers->set('Authorization', 'Bearer segredo-correto');
        $middleware = new AuthenticateMcpHttp();

        $called = false;
        $response = $middleware->handle($request, function ($req) use (&$called) {
            $called = true;

            return response('ok');
        });

        $this->assertTrue($called);
        $this->assertSame(200, $response->getStatusCode());
    }
}
```

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `php artisan test tests/Unit/Http/Middleware/AuthenticateMcpHttpTest.php`
Expected: FAIL — `Class "App\Http\Middleware\AuthenticateMcpHttp" not found`

- [ ] **Step 3: Implementar o middleware**

Create `app/Http/Middleware/AuthenticateMcpHttp.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autenticação própria da rota HTTP do MCP (`POST /api/mcp`) — não é o
 * Sanctum de usuário (outro propósito: token secreto dedicado, sem usuário
 * associado). 401 se o header `Authorization: Bearer <MCP_HTTP_TOKEN>`
 * estiver ausente, errado, ou se `MCP_HTTP_TOKEN` não estiver configurado —
 * nunca deixa a rota aberta por omissão de config.
 */
class AuthenticateMcpHttp
{
    public function handle(Request $request, Closure $next): Response
    {
        $configured = config('mcp.http_token');
        $provided = $request->bearerToken();

        if (empty($configured) || empty($provided) || ! hash_equals((string) $configured, (string) $provided)) {
            return response()->json([
                'message' => 'Token de acesso ao MCP HTTP ausente ou inválido.',
            ], 401);
        }

        return $next($request);
    }
}
```

- [ ] **Step 4: Registrar o alias em `bootstrap/app.php`**

In `bootstrap/app.php`, add the import and extend the existing `$middleware->alias([...])` array (do not remove `abilities`/`ability`):

```php
use App\Http\Middleware\AuthenticateMcpHttp;
```

```php
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
            'mcp.http-token' => AuthenticateMcpHttp::class,
        ]);
```

- [ ] **Step 5: Rodar e confirmar que passa**

Run: `php artisan test tests/Unit/Http/Middleware/AuthenticateMcpHttpTest.php`
Expected: PASS (4 testes)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Middleware/AuthenticateMcpHttp.php bootstrap/app.php tests/Unit/Http/Middleware/AuthenticateMcpHttpTest.php
git commit -m "feat: middleware de autenticação dedicada da rota HTTP do MCP (MCP_HTTP_TOKEN)

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 5: Rota, controller e rate limit (`POST /api/mcp`)

**Files:**
- Create: `app/Http/Controllers/Api/McpHttpController.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `routes/api.php`

**Interfaces:**
- Consumes: `App\Mcp\McpServerFactory::buildStatelessProtocol()` (Task 3), middleware `mcp.http-token` (Task 4), `Mcp\Server\Transport\StatelessHttpTransport` (SDK), `Symfony\Bridge\PsrHttpMessage\Factory\{PsrHttpFactory,HttpFoundationFactory}` (Task 1).
- Produces: rota `POST /api/mcp` — consumida pelos testes da Task 6.

Sem teste unitário dedicado ao controller nesta task — ele é exercitado de ponta a ponta pelos testes de Feature da Task 6 (é justamente o ponto do plano: testar pela rota real, não a lógica isolada).

- [ ] **Step 1: Registrar o rate limiter nomeado `mcp`**

In `app/Providers/AppServiceProvider.php`, inside the existing `boot()` method, add (keep the existing `login`/`password-reset` limiters untouched):

```php
        // MCP HTTP: read-only, mas ainda consulta dado real — throttle básico
        // por IP pra não virar vetor de abuso mesmo sendo GET-only por baixo.
        RateLimiter::for('mcp', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });
```

- [ ] **Step 2: Criar o controller**

Create `app/Http/Controllers/Api/McpHttpController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Mcp\McpServerFactory;
use Illuminate\Http\Request;
use Mcp\Server\Transport\StatelessHttpTransport;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ponto de entrada HTTP do servidor MCP — alternativa ao STDIO de
 * `php artisan mcp:serve`, pro mesmo conjunto de 29 tools. Stateless: cada
 * requisição é uma chamada MCP isolada, via
 * `Mcp\Server\Transport\StatelessHttpTransport` do SDK oficial (não
 * reimplementa o protocolo aqui — só converte Laravel <-> PSR-7).
 *
 * Autenticação (`mcp.http-token`) e rate limit (`throttle:mcp`) ficam na
 * definição da rota (routes/api.php), não aqui.
 */
class McpHttpController
{
    public function __construct(private readonly McpServerFactory $factory) {}

    public function __invoke(Request $request): Response
    {
        $psrRequest = (new PsrHttpFactory())->createRequest($request);

        $transport = new StatelessHttpTransport($this->factory->buildStatelessProtocol());
        $psrResponse = $transport->handle($psrRequest);

        return (new HttpFoundationFactory())->createResponse($psrResponse);
    }
}
```

- [ ] **Step 3: Registrar a rota**

In `routes/api.php`, add the import:

```php
use App\Http\Controllers\Api\McpHttpController;
```

And add the route near the other public-but-token-gated routes (e.g. right after `/login`, before the `auth:sanctum` group — it must NOT be inside the `auth:sanctum`/`abilities:access` group, since its auth is the new dedicated middleware, not Sanctum):

```php
// Servidor MCP via HTTP — autenticação própria (mcp.http-token), NÃO Sanctum
// de usuário. Ver docs/MCP.md "Transporte HTTP".
Route::post('mcp', McpHttpController::class)->middleware(['mcp.http-token', 'throttle:mcp']);
```

- [ ] **Step 4: Verificação manual — a rota responde (mesmo que só com 401 nesta hora, sem token)**

Run (with the local dev server running, or via `php artisan route:list` first to confirm registration):

```bash
php artisan route:list --path=mcp
```

Expected: shows `POST api/mcp` with both middleware (`mcp.http-token`, `throttle:mcp`) listed. This confirms wiring before the Feature tests in Task 6 drive it end to end.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/McpHttpController.php app/Providers/AppServiceProvider.php routes/api.php
git commit -m "feat: rota POST /api/mcp (transporte HTTP do MCP via StatelessHttpTransport)

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 6: Testes de Feature (round-trip real via HTTP)

**Files:**
- Create: `tests/Feature/McpHttpTest.php`

**Interfaces:** nenhuma nova — só exercita a rota real construída nas Tasks 3-5.

Esta é a task mais delicada do plano. O protocolo MCP na revisão moderna/stateless (SEP-2575, a que `StatelessProtocol` fala) **NÃO usa `initialize`** — esse método foi removido nessa era e responde como método desconhecido. O formato de wire correto (headers + corpo JSON-RPC) está documentado e testado no próprio repositório do SDK oficial, em `examples/server/README.md` e `tests/Integration/StatelessLifecycleTest.php`. Abaixo está o formato exato, copiado de lá — **use-o como está**, não invente um formato baseado na era clássica (a que o STDIO usa, com `initialize`/`serverInfo.name`).

**Formato de requisição (headers obrigatórios):**

```
Content-Type: application/json
Accept: application/json, text/event-stream
MCP-Protocol-Version: 2026-07-28
Mcp-Method: <method>
Mcp-Name: <tool name, SÓ quando o method for tools/call>
```

**Formato do corpo** (`params._meta` é obrigatório em toda requisição):

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "server/discover",
  "params": {
    "_meta": {
      "io.modelcontextprotocol/protocolVersion": "2026-07-28",
      "io.modelcontextprotocol/clientCapabilities": {}
    }
  }
}
```

Pra `tools/call`, `params` ganha `name`/`arguments` além do `_meta`:

```json
{
  "jsonrpc": "2.0",
  "id": 2,
  "method": "tools/call",
  "params": {
    "name": "list_sales",
    "arguments": {},
    "_meta": {
      "io.modelcontextprotocol/protocolVersion": "2026-07-28",
      "io.modelcontextprotocol/clientCapabilities": {}
    }
  }
}
```

**Formato da resposta de `server/discover`** (confirmado no teste oficial do SDK): a identidade do servidor vem em `result._meta['io.modelcontextprotocol/serverInfo']['name']` — **não** em `result.serverInfo.name` (isso é da era clássica, não desta). Também vem `result.resultType === 'complete'`.

**Formato da resposta de `tools/call`**: `result.content[0].text` (texto), e — isso ainda precisa ser confirmado empiricamente contra a implementação real deste projeto, não assumido — provavelmente `result.isError === true` quando o `ToolHandler` lança `ToolCallException` (mesmo `CallToolResult`/`ToolResultFormatter` compartilhado entre as eras, per o código-fonte do SDK). **Verifique isso rodando o teste antes de travar a asserção final** — se `isError` não aparecer exatamente assim, inspecione a resposta real (`dd($response->json())` ou similar) e ajuste a asserção pro que a resposta realmente é, documentando no relatório o que encontrou.

- [ ] **Step 1: Escrever os testes**

Create `tests/Feature/McpHttpTest.php`:

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class McpHttpTest extends TestCase
{
    private const HEADERS_BASE = [
        'Accept' => 'application/json, text/event-stream',
        'MCP-Protocol-Version' => '2026-07-28',
    ];

    public function test_returns_401_without_authorization_header(): void
    {
        $response = $this->withHeaders(self::HEADERS_BASE + ['Mcp-Method' => 'server/discover'])
            ->postJson('/api/mcp', $this->discoverBody());

        $response->assertStatus(401);
    }

    public function test_returns_401_with_wrong_token(): void
    {
        $response = $this->withHeaders(self::HEADERS_BASE + [
            'Mcp-Method' => 'server/discover',
            'Authorization' => 'Bearer token-errado',
        ])->postJson('/api/mcp', $this->discoverBody());

        $response->assertStatus(401);
    }

    public function test_returns_401_when_mcp_http_token_not_configured(): void
    {
        config(['mcp.http_token' => null]);

        $response = $this->withHeaders(self::HEADERS_BASE + [
            'Mcp-Method' => 'server/discover',
            'Authorization' => 'Bearer '.config('mcp.http_token'), // null vira string vazia
        ])->postJson('/api/mcp', $this->discoverBody());

        $response->assertStatus(401);
    }

    public function test_server_discover_round_trip_with_valid_token(): void
    {
        $response = $this->withHeaders(self::HEADERS_BASE + [
            'Mcp-Method' => 'server/discover',
            'Authorization' => 'Bearer '.config('mcp.http_token'),
        ])->postJson('/api/mcp', $this->discoverBody());

        $response->assertOk();
        $response->assertJsonPath('result._meta.io\\.modelcontextprotocol/serverInfo.name', 'erp-mcp-php');
    }

    public function test_tools_call_round_trip_surfaces_real_error_not_generic_failure(): void
    {
        $response = $this->withHeaders(self::HEADERS_BASE + [
            'Mcp-Method' => 'tools/call',
            'Mcp-Name' => 'list_sales',
            'Authorization' => 'Bearer '.config('mcp.http_token'),
        ])->postJson('/api/mcp', $this->toolCallBody('list_sales', []));

        $response->assertOk();

        // ERP_API_TOKEN não está setado no ambiente de teste (só MCP_HTTP_TOKEN
        // está) — então a chamada real à API do MiniERP deve falhar, e essa
        // falha real deve aparecer na resposta em vez de um -32603 genérico.
        // A asserção exata de "onde" (isError vs. outro campo) foi deixada
        // para você confirmar rodando o teste primeiro — ver a nota acima do
        // Step 1. Ajuste esta asserção pro formato real observado.
        $body = $response->json();
        $this->assertStringContainsString('ERP_API_TOKEN', json_encode($body));
    }

    public function test_rate_limit_returns_429_after_the_configured_number_of_requests(): void
    {
        $headers = self::HEADERS_BASE + [
            'Mcp-Method' => 'server/discover',
            'Authorization' => 'Bearer '.config('mcp.http_token'),
        ];

        for ($i = 0; $i < 30; $i++) {
            $this->withHeaders($headers)->postJson('/api/mcp', $this->discoverBody());
        }

        $response = $this->withHeaders($headers)->postJson('/api/mcp', $this->discoverBody());

        $response->assertStatus(429);
    }

    /** @return array<string, mixed> */
    private function discoverBody(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'server/discover',
            'params' => [
                '_meta' => [
                    'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
                    'io.modelcontextprotocol/clientCapabilities' => new \stdClass(),
                ],
            ],
        ];
    }

    /** @param array<string, mixed> $arguments
     *  @return array<string, mixed> */
    private function toolCallBody(string $name, array $arguments): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/call',
            'params' => [
                'name' => $name,
                'arguments' => $arguments,
                '_meta' => [
                    'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
                    'io.modelcontextprotocol/clientCapabilities' => new \stdClass(),
                ],
            ],
        ];
    }
}
```

Note on `assertJsonPath('result._meta.io\\.modelcontextprotocol/serverInfo.name', ...)`: Laravel's `assertJsonPath` splits the path on literal dots, and the real JSON key here (`io.modelcontextprotocol/serverInfo`) contains a dot itself. If the escaped-dot syntax above doesn't work against this Laravel version, fall back to asserting on the decoded array directly instead of fighting the dot-path syntax:

```php
$this->assertSame('erp-mcp-php', $response->json('result._meta')['io.modelcontextprotocol/serverInfo']['name']);
```

Use whichever form actually works — confirm by running the test, don't guess.

- [ ] **Step 2: Rodar e ver o que realmente acontece — primeiro só o `server/discover`, isolado**

Run: `php artisan test tests/Feature/McpHttpTest.php --filter=test_server_discover_round_trip_with_valid_token -v`

Se a asserção de `assertJsonPath`/array falhar por causa de sintaxe, ou se a resposta não tiver o shape esperado, adicione um `dd($response->json())` temporário nesse teste, rode de novo, veja o JSON real, ajuste a asserção pro que existe de verdade, e remova o `dd()`. **Não force uma asserção baseada só no que este plano descreve** — o plano documenta o formato conhecido do SDK, mas a implementação real (rota, middleware, controller, `StatelessProtocol`) é sua responsabilidade verificar.

- [ ] **Step 3: Rodar o `tools/call` isolado e confirmar como o erro real aparece**

Run: `php artisan test tests/Feature/McpHttpTest.php --filter=test_tools_call_round_trip_surfaces_real_error_not_generic_failure -v`

Mesma instrução: se a asserção genérica (`assertStringContainsString('ERP_API_TOKEN', ...)`) não bater porque o shape é diferente do esperado, inspecione a resposta real e ajuste. O que NÃO pode acontecer é a resposta ser um erro genérico tipo `-32603`/`"Error while executing tool"` sem a mensagem real do `ErpApiClient` — se isso acontecer, é um bug de verdade (o mesmo tipo do que foi achado na rodada anterior), não um problema de asserção — pare e reporte BLOCKED.

- [ ] **Step 4: Rodar a suíte inteira do arquivo**

Run: `php artisan test tests/Feature/McpHttpTest.php`
Expected: PASS — 6 testes (3× 401, discover, tools/call, rate limit).

- [ ] **Step 5: Rodar a suíte inteira do projeto**

Run: `composer test`
Expected: PASS, 0 falha, sem regressão em nada (STDIO incluído).

- [ ] **Step 6: Commit**

```bash
git add tests/Feature/McpHttpTest.php
git commit -m "test: round-trip HTTP real do transporte MCP (401s, discover, tools/call, rate limit)

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 7: Documentação (`docs/MCP.md` + `README.md`)

**Files:**
- Modify: `docs/MCP.md`
- Modify: `README.md`

**Interfaces:** nenhuma (documentação).

- [ ] **Step 1: Ler `docs/MCP.md` primeiro**

Read the current `docs/MCP.md` in full — the edit below adds a new section; place it logically (e.g. right after the existing "Registrar no Claude Code / Claude Desktop" section, since this is a second way to do that same registration).

- [ ] **Step 2: Adicionar a seção "Transporte HTTP" em `docs/MCP.md`**

Insert a new section (exact content below, adjust only the surrounding heading levels to fit where you place it):

````markdown
## Transporte HTTP (produção, sem STDIO local)

Além do STDIO (`php artisan mcp:serve`, rodado localmente como subprocesso), este servidor também responde ao protocolo MCP via HTTP, direto no mesmo deploy Railway já existente — sem subir serviço novo. Rota: `POST /api/mcp`.

Use isso quando quiser apontar um cliente MCP direto pra produção, sem rodar nada localmente.

### Autenticação

Token dedicado, **não** é o Sanctum de usuário nem o `ERP_API_TOKEN` (aquele autentica ESTE servidor contra a API do MiniERP; este autentica QUEM pode falar com o servidor MCP). Header `Authorization: Bearer <MCP_HTTP_TOKEN>`.

Gerar um token novo:

```bash
openssl rand -hex 32
```

Definir no Railway: no serviço do backend, aba de variáveis de ambiente, adicionar `MCP_HTTP_TOKEN` com o valor gerado. Não precisa de redeploy de código — só a env var.

**Aviso de segurança**: essa rota fica exposta no mesmo domínio público do Railway. Trate o `MCP_HTTP_TOKEN` como segredo (não commitar, não logar). Se vazar, gere um novo com o comando acima e troque a env var no Railway — isso invalida o antigo imediatamente, sem precisar de nenhuma outra ação.

### Rate limit

30 requisições/minuto por IP (`throttle:mcp`, ver `app/Providers/AppServiceProvider.php`). Read-only, mas ainda consulta dado real — o limite existe pra não virar vetor de abuso.

### Registrar no Claude Code / Claude Desktop (via HTTP)

```json
{
  "mcpServers": {
    "erp-mcp-php-http": {
      "url": "https://laravel-production-4c67.up.railway.app/api/mcp",
      "headers": {
        "Authorization": "Bearer <seu-MCP_HTTP_TOKEN-aqui>"
      }
    }
  }
}
```

(Formato de config HTTP varia por cliente MCP — confira a documentação do seu cliente pro campo exato de headers customizados; alguns aceitam `headers` direto na entrada do servidor, outros pedem uma flag de linha de comando equivalente.)
````

- [ ] **Step 3: Atualizar a nota "dois servidores MCP" (agora é "dois transportes" também)**

Find the existing "## Dois servidores MCP pra este backend" section and add one sentence right after its first paragraph, noting the PHP server itself now has two transports:

```markdown
(O servidor PHP em si também tem dois *transportes* — STDIO local e HTTP em produção, ver a seção "Transporte HTTP" acima — isso é ortogonal à distinção Node/PHP: são dois eixos diferentes.)
```

- [ ] **Step 4: Atualizar `README.md`**

In the existing "## Servidor MCP (PHP)" section, add one sentence: mention that it's now reachable both via STDIO and via `POST /api/mcp` over HTTP in production, linking the same `docs/MCP.md`.

- [ ] **Step 5: Commit**

```bash
git add docs/MCP.md README.md
git commit -m "docs: transporte HTTP do servidor MCP (MCP_HTTP_TOKEN, rate limit, config de cliente)

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 8: Verificação final

**Files:** nenhum novo — só verificação.

- [ ] **Step 1: `composer install` limpo**

Run: `rm -rf vendor && composer install`
Expected: instala sem erro.

- [ ] **Step 2: Suíte inteira**

Run: `composer test`
Expected: tudo passando (baseline + os novos testes desta plan), 0 falha.

- [ ] **Step 3: Pint**

Run: `vendor/bin/pint app/Mcp app/Http/Controllers/Api/McpHttpController.php app/Http/Middleware/AuthenticateMcpHttp.php app/Providers/AppServiceProvider.php app/Console/Commands/McpServeCommand.php tests/Unit/Mcp tests/Unit/Http/Middleware tests/Feature/McpHttpTest.php --test`

Se reportar violação, rodar sem `--test`, revisar o diff, rodar `composer test` de novo.

- [ ] **Step 4: Confirmar (de novo, explicitamente) que o STDIO continua funcionando**

Run:
```bash
echo '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"smoke-test","version":"0.0.0"}}}' | php artisan mcp:serve
```
Expected: JSON no stdout com `serverInfo.name = "erp-mcp-php"` — igual sempre foi. Este é o requisito explícito do usuário: STDIO 100% intocado.

- [ ] **Step 5: Confirmar a rota HTTP registrada**

Run: `php artisan route:list --path=mcp`
Expected: `POST api/mcp` com middleware `mcp.http-token`, `throttle:mcp`.

- [ ] **Step 6: Commit final (se o Pint mudou algo)**

```bash
git add -A
git commit -m "chore: formatação Pint no transporte HTTP do MCP

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

(Pular este commit se o Step 3 não mudou nada.)

---

## Fora deste plano (decisões operacionais)

- Gerar e setar o `MCP_HTTP_TOKEN` real de produção no Railway é ação do usuário — a doc explica como, não é feito por este trabalho.
- Commit numa branch (mesma `worktree-mcp-php-server` já em uso, que já tem PR #2 aberta — isto estende essa PR). Push e atualização da PR: fora deste plano, decisão pós-implementação.
