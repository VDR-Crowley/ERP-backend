# Design: transporte HTTP pro servidor MCP em PHP

## Contexto

O servidor MCP em PHP (`app/Mcp/*`, `php artisan mcp:serve`) já existe, hospedado dentro deste backend, expondo 29 tools de leitura via transporte STDIO — ver `docs/superpowers/specs/2026-09-10-mcp-php-server-design.md` e `docs/MCP.md`. STDIO exige rodar o comando localmente como subprocesso; não dá pra apontar um cliente remoto direto pra produção sem SSH/exec.

Este design adiciona um **segundo transporte**, HTTP, reaproveitando o deploy Railway já existente (sem subir serviço novo) e o SDK oficial `mcp/sdk`, que já suporta HTTP nativamente. STDIO continua existindo, intocado — este é um transporte adicional, não substituição.

## Escopo

- Nova rota `POST /api/mcp` falando o protocolo MCP via HTTP, usando `Mcp\Server\Transport\StatelessHttpTransport` do SDK oficial (não reimplementar o protocolo na mão).
- `App\Mcp\McpServerFactory` ganha um método novo, `buildStatelessProtocol(): \Mcp\Server\Stateless\StatelessProtocol`, reusando a mesma configuração de tools (`ToolDefinitions`, `ToolHandler`, `ErpApiClient`) já usada por `build(): \Mcp\Server` (STDIO). `build()` não muda.
- Autenticação dedicada (não Sanctum de usuário): header `Authorization: Bearer <MCP_HTTP_TOKEN>` comparado via `hash_equals` a `config('mcp.http_token')` (env `MCP_HTTP_TOKEN`). 401 claro se ausente, errado, ou não configurado.
- Rate limiting: `throttle:30,1` (30 requisições/minuto) na rota.
- Documentação atualizada (`docs/MCP.md` + README se necessário): duas formas de acesso (STDIO local, HTTP em produção), como configurar cliente MCP pra HTTP + token, como gerar/setar `MCP_HTTP_TOKEN` no Railway, aviso de segurança (endpoint público, token é segredo, rotacionar se vazar).

Fora de escopo: sessões/streaming (SSE), qualquer transporte além de STDIO+HTTP stateless, autenticação de usuário final (Sanctum), rate limiting avançado (por IP com Redis, etc — o `throttle` padrão do Laravel já opera por IP/usuário via cache).

## Arquitetura

### Por que `StatelessHttpTransport` (não `StreamableHttpTransport`)

O SDK oferece dois transportes HTTP. `StreamableHttpTransport` existe pra gerenciar sessão (GET stream de eventos, DELETE de teardown) — não faz sentido aqui: cada chamada MCP vira uma requisição HTTP única, sem estado entre chamadas, exatamente o modelo "POST in, one message out" que `StatelessHttpTransport` documenta como seu propósito. Menos superfície, menos coisa pra manter.

### Ponte Laravel ↔ PSR-7

`StatelessHttpTransport::handle()` fala PSR-7 (`Psr\Http\Message\ServerRequestInterface` → `ResponseInterface`); Laravel fala `Illuminate\Http\Request`/`Response` (Symfony HttpFoundation). Ponte: `symfony/psr-http-message-bridge` (pacote oficial Symfony), nova dependência de `composer.json`. As factories PSR-17 que a ponte precisa (`RequestFactoryInterface`, `ResponseFactoryInterface`, etc.) vêm de `guzzlehttp/psr7` — já instalado transitivamente (client HTTP do Laravel é Guzzle-based) — via `GuzzleHttp\Psr7\HttpFactory`, que implementa as seis interfaces PSR-17 de uma vez. Nenhuma dependência nova nesse lado.

Importante: a conversão usa `Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory::createRequest($illuminateRequest)`, que lê o objeto `Request` já populado (método, headers, `getContent()`) — não superglobais/`php://input` direto. Isso importa pra teste: o cliente de teste HTTP do Laravel (`postJson`) constrói um `Request` em memória sem passar pelo SAPI real, então uma implementação que lesse `php://input`/`$_SERVER` diretamente (ex.: `GuzzleHttp\Psr7\ServerRequest::fromGlobals()`) veria corpo vazio dentro de teste e o round-trip real quebraria silenciosamente. A ponte oficial evita esse problema.

### Fluxo da requisição

```
POST /api/mcp
  → middleware `mcp.http-token` (novo) — 401 se token ausente/errado/não configurado
  → middleware `throttle:30,1`
  → App\Http\Controllers\Api\McpHttpController (invokable, novo)
      1. Illuminate\Http\Request → PSR-7 ServerRequestInterface (via PsrHttpFactory)
      2. App\Mcp\McpServerFactory::buildStatelessProtocol() → StatelessProtocol
      3. new StatelessHttpTransport($protocol) → handle($psrRequest) → PSR-7 ResponseInterface
      4. PSR-7 ResponseInterface → Symfony\Component\HttpFoundation\Response (via HttpFoundationFactory)
```

Uma nova instância de `StatelessHttpTransport`/`StatelessProtocol` por requisição é aceitável — é stateless por definição, e o custo de rebuild (loop sobre 29 definições) é desprezível frente a uma chamada HTTP de rede.

### `McpServerFactory::buildStatelessProtocol()`

```php
public function buildStatelessProtocol(): StatelessProtocol
{
    $registry = new Registry();
    $this->registry = $registry;

    $builder = Server::builder()
        ->setServerInfo('erp-mcp-php', '1.0.0')
        ->setRegistry($registry);

    $this->registerTools($builder);

    return $builder->buildStateless();
}
```

Refatoração: o loop de `addTool()`/registro (hoje duplicado inline em `build()`) vira um método privado `registerTools(Builder $builder): void` chamado por ambos `build()` e `buildStatelessProtocol()`, pra não duplicar a lista de 29 tools/schema/annotations entre os dois métodos. `build()` continua com a mesma assinatura e comportamento — nenhum teste existente de `McpServerFactoryTest`/`McpServeCommandTest`/`McpProtocolRoundTripTest` deve mudar de expectativa.

### Config (`config/mcp.php` + `McpConfig`)

Nova chave `http_token`, mesma função de resolução já existente:

```php
'http_token' => McpConfig::resolveToken(env('MCP_HTTP_TOKEN')),
```

Nenhum método novo em `McpConfig` — `resolveToken` (trim + blank-como-null) já serve pro mesmo formato de token.

### Middleware de autenticação (`App\Http\Middleware\AuthenticateMcpHttp`)

```php
public function handle(Request $request, Closure $next): Response
{
    $configured = config('mcp.http_token');
    $provided = $request->bearerToken();

    if (empty($configured) || empty($provided) || !hash_equals($configured, $provided)) {
        return response()->json(['message' => 'Token de acesso ao MCP HTTP ausente ou inválido.'], 401);
    }

    return $next($request);
}
```

Registrado como alias `mcp.http-token` em `bootstrap/app.php` (`$middleware->alias([...])`, mesmo padrão já usado ali pras abilities do Sanctum). `hash_equals` evita timing attack na comparação. `empty($configured)` cobre o caso "esqueceram de setar a env" — nunca deixa a rota aberta por omissão.

### Rota (`routes/api.php`)

```php
Route::post('mcp', McpHttpController::class)->middleware(['mcp.http-token', 'throttle:30,1']);
```

Path final: `POST /api/mcp` (prefixo `/api` automático, igual todo o resto de `routes/api.php`). Fora do grupo `auth:sanctum` existente — autenticação própria, não a de usuário.

## Testes

`tests/Feature/McpHttpTest.php` (Feature, não Unit — bate na stack HTTP real via `postJson`, então convém a pasta `tests/Feature/` já usada por este repo pra esse tipo de teste, ver `tests/Feature/Auth/`):

1. 401 sem header `Authorization`.
2. 401 com token errado.
3. 401 quando `MCP_HTTP_TOKEN` não está configurado (mesmo com *algum* header presente).
4. 200 com token certo, corpo de `initialize` válido → resposta JSON-RPC real com `serverInfo.name = "erp-mcp-php"` (prova que a stack completa — rota, middleware, ponte PSR-7, `StatelessProtocol`, registro de tools — funciona de ponta a ponta, não só a lógica isolada).
5. 200 com token certo, `tools/call` de um tool zero-arg (ex. `list_sales`) → resposta com `isError: true` e mensagem real de erro (token da API não configurado no ambiente de teste), confirmando que o mesmo bug da rodada anterior (closure não recebendo argumento) não existe também no caminho HTTP.
6. Rate limit: uma chamada além do limite retorna 429 (testável via múltiplas chamadas no teste, ou verificação de que a rota carrega o middleware `throttle` — decisão de implementação, documentar qual).

Reusa `McpConfigTest`-style: mais um teste opcional em `tests/Unit/Mcp/McpConfigTest.php` pra `resolveToken` aplicado a `MCP_HTTP_TOKEN` não é necessário — já é a mesma função testada.

## Documentação

`docs/MCP.md` ganha:
- Seção nova, "Duas formas de acesso: STDIO (local) e HTTP (produção)".
- Como configurar um cliente MCP HTTP: exemplo JSON apontando pra `https://laravel-production-4c67.up.railway.app/api/mcp` com header `Authorization: Bearer <MCP_HTTP_TOKEN>` (formato exato de config MCP client sobre HTTP — usar o formato que o SDK/clientes MCP esperam pra transporte HTTP, não STDIO).
- Como gerar `MCP_HTTP_TOKEN` (ex.: `openssl rand -hex 32`) e setar no Railway (variável de ambiente do serviço).
- Aviso de segurança: endpoint fica público na internet (mesmo domínio já público do Railway), tratar o token como segredo, rotacionar se vazar (basta trocar a env var no Railway, sem redeploy de código).

`README.md`: uma frase a mais na seção "Servidor MCP (PHP)" já existente, mencionando o modo HTTP e linkando a mesma seção de `docs/MCP.md`.

## Fora do design (decisões operacionais)

- Gerar e setar o `MCP_HTTP_TOKEN` real no Railway é ação do usuário fora deste repo — a doc explica como, não é feito por este trabalho.
- Commit: numa branch, sem merge direto em `main` — abrir PR (mesma branch/worktree do trabalho anterior, `worktree-mcp-php-server`, que já tem PR #2 aberta; isto estende essa PR em vez de abrir uma segunda).
