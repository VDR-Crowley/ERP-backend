# Design: servidor MCP em PHP (dentro do ERP-Backend)

## Contexto

Já existe um servidor MCP de leitura em Node/TypeScript, `../ERP-MCP`, que expõe 29 tools geradas a partir dos endpoints `GET` de `ERP-Backend/docs/openapi.yaml`. Esse projeto Node **não é alterado** por este trabalho — continua existindo e funcionando como está.

Este design cobre um segundo servidor MCP, equivalente em escopo e comportamento, escrito em PHP e vivendo dentro deste próprio repositório (`ERP-Backend`), usando o SDK oficial `mcp/sdk` (pacote Packagist do projeto `modelcontextprotocol/php-sdk`, mantido por Symfony + PHP Foundation).

Razão: agora que existe um SDK oficial em PHP, faz sentido ter a versão read-only nativa no mesmo toolchain do backend (PHP/Laravel), sem precisar manter Node instalado só pra isso. O Node foi feito primeiro porque na época não existia SDK oficial em PHP.

## Escopo

Espelhar 1:1 (mesmos nomes, mesmas descrições, mesmo comportamento):

- As 29 tools do Node (uma por endpoint `GET` do `openapi.yaml`): `get_current_user`, `list_users`, `list_products`, `get_product`, `list_vendedores`, `get_vendedor`, `list_flock`, `get_flock`, `list_flock_incubations`, `get_flock_incubation`, `list_hatch_events`, `list_vendor_stock`, `get_vendor_stock`, `list_sales`, `get_sale`, `list_stock_transfers`, `get_stock_transfer`, `list_daily_productions`, `get_daily_production`, `list_expenses`, `get_expense`, `list_cash_flows`, `get_cash_flow`, `list_feed_stocks`, `get_feed_stock`, `list_feed_open_logs`, `list_flock_cleanings`, `get_flock_cleaning`, `get_business_line_report`.
- Guarda GET-only na camada de HTTP client.
- Config via env vars (`ERP_API_BASE_URL`, `ERP_API_TOKEN`).
- Exposição de erro real da API (corpo + status) em respostas não-2xx.
- Transporte STDIO.
- Testes PHPUnit.
- Documentação no README/docs.

Fora de escopo (igual ao Node): qualquer tool de escrita, qualquer transporte além de STDIO, refresh automático de token.

## Arquitetura

Tudo vive dentro da app Laravel existente, sem subprojeto separado:

```
app/Mcp/
  ToolDefinitions.php      # array estático, 1 entrada por tool (nome, descrição, path, path params, query params)
  ErpApiClient.php         # HTTP client GET-only
  ErpApiException.php      # erro não-2xx da API (status, corpo, url)
  MissingTokenException.php
  ApiUnreachableException.php
  McpServerFactory.php     # monta Mcp\Server a partir de ToolDefinitions + ErpApiClient
app/Console/Commands/
  McpServeCommand.php      # `php artisan mcp:serve`, transporte StdioTransport
config/
  mcp.php                  # base_url / token, lidos de env
tests/Unit/Mcp/
  ToolDefinitionsTest.php
  ErpApiClientGetOnlyTest.php
  ErpApiClientAuthHeaderTest.php
  ErpApiClientUrlBuildingTest.php
  ErpApiClientErrorExposureTest.php
  McpServerFactoryTest.php
```

### `config/mcp.php`

```php
return [
    'base_url' => rtrim(env('ERP_API_BASE_URL', config('app.url').'/api'), '/'),
    'token' => env('ERP_API_TOKEN'),
];
```

Decisão: default de `base_url` usa `config('app.url')` do próprio Laravel, não uma URL de produção hardcoded (diferente do Node, que por ser repo separado hardcoda a URL do Railway como fallback). Faz mais sentido aqui porque o MCP já mora dentro do backend — rodar local sem configurar nada aponta pro próprio `APP_URL` local. Continua sobrescrevível via `ERP_API_BASE_URL` pra apontar pra produção.

### `ErpApiClient`

Único método público: `get(string $path, array $pathParams = [], array $query = []): array`.

Internamente:
- Método privado `request(string $method, ...)` recusa qualquer `$method !== 'GET'` lançando exceção — defesa em profundidade idêntica ao Node, mesmo que nunca seja chamado com outro verbo pelo código atual.
- Usa `Illuminate\Support\Facades\Http` (client HTTP nativo do Laravel) em vez de adicionar um client PSR novo — reaproveita o que o Laravel já resolve, mais idiomático pro repo.
- Sem token configurado → lança `MissingTokenException` antes de tentar a request.
- Substitui `{param}` no path pelos `pathParams` (erro se faltar um).
- Monta query string ignorando valores `null`/ausentes.
- Envia `Authorization: Bearer <token>` e `Accept: application/json`.
- Falha de rede/DNS/timeout → `ApiUnreachableException` (mensagem inclui URL e causa).
- Resposta não-2xx → `ErpApiException` carregando status, corpo (JSON parseado ou texto cru) e URL; mensagem já formatada com casos especiais pra 401/403/404 (mesmo texto explicativo do Node, adaptado).

### `ToolDefinitions`

Array PHP (`name`, `description`, `path`, `pathParams`, `queryParams`) — fonte única de verdade, gerada à mão a partir do `openapi.yaml` (mesmo processo do Node: hand-authored, não codegen cego). Path params viram `{"type": "integer", "minimum": 1}` obrigatório no JSON Schema de input — mesma regra do Node (`z.number().int().positive()`), IDs de route-model-binding do Laravel. Query params (hoje só `start`/`end` em `get_business_line_report`) são `{"type": "string", "pattern": "^\\d{4}-\\d{2}-\\d{2}$"}` opcional (não entram no array `required`), igual ao Node.

### `McpServerFactory`

```php
$builder = Server::builder()->setServerInfo('erp-mcp-php', '1.0.0');

foreach (ToolDefinitions::all() as $def) {
    $builder->addTool(
        handler: function (array $args) use ($def, $apiClient) {
            try {
                return $apiClient->get($def->path, splitPathParams($def, $args), splitQueryParams($def, $args));
            } catch (\Throwable $e) {
                throw new \Mcp\Exception\ToolCallException($e->getMessage());
            }
        },
        name: $def->name,
        description: $def->description,
        inputSchema: buildInputSchema($def),
        annotations: new ToolAnnotations(readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false),
    );
}

return $builder->build();
```

`ToolCallException` é reconhecida pelo próprio SDK (`CallToolHandler`) e vira `CallToolResult::error([TextContent($mensagem)])` — ou seja, o cliente MCP recebe `isError: true` com a mensagem real, mesmo padrão do Node (`isError: true` + `content` com a mensagem).

### `McpServeCommand`

`php artisan mcp:serve`. Resolve `McpServerFactory` via container, chama `$server->run(new StdioTransport())`. Se `ERP_API_TOKEN` não estiver setado, escreve aviso em **STDERR** (`fwrite(STDERR, ...)`, nunca `$this->info()`/stdout) — igual ao Node, porque STDOUT é o canal do protocolo JSON-RPC e não pode ser poluído.

## Testes (PHPUnit, `tests/Unit/Mcp/`)

Extends `Tests\TestCase` (boota a app Laravel), usa `Http::fake()` (padrão já usado no Laravel, sem HTTP real):

1. **Config**: `config('mcp.base_url')`/`token` refletem env vars; default de `base_url` cai pro `app.url` quando `ERP_API_BASE_URL` não setado.
2. **Guarda GET-only**: chamar o método interno com verbo != GET (via reflection) lança exceção; `ErpApiClient` não expõe nenhum outro método HTTP público (post/put/patch/delete).
3. **Header de auth**: `Http::fake()` captura a request e assert `Authorization: Bearer <token>`.
4. **Build de URL/query**: path params substituídos corretamente, query params `null` omitidos, query params presentes aparecem na URL.
5. **Exposição de erro**: 401, 404, 502 (corpo real repassado na mensagem/exceção), rede inacessível (`Http::fake` lançando `ConnectionException` ou similar), token ausente (`MissingTokenException` antes de qualquer request).
6. **Registro das tools**: `McpServerFactory` registra exatamente 29 tools, nomes batem com a lista esperada, cada uma mapeia pro path GET correto.

## Documentação

Nova seção `docs/MCP.md`, linkada do `README.md` principal (README já é longo e estruturado por tópicos — um doc separado, linkado, é mais limpo que inflar ainda mais o README). Cobre:

- O que é / por que existe (mesma razão do Node, agora PHP-nativo).
- Instalação: `composer install` (já traz `mcp/sdk` como dependência do projeto).
- Configuração: `ERP_API_BASE_URL` / `ERP_API_TOKEN`, como pegar token via `POST /login`, ressalva honesta de que o token tem acesso total à API — o limite read-only é só na camada MCP.
- Registro no Claude Code/Claude Desktop: snippet JSON com `command: php`, `args: ["artisan", "mcp:serve"]`, `cwd` apontando pro repo, env vars.
- Tabela das 29 tools.
- Nota: agora existem DOIS servidores MCP pra este backend (Node em `../ERP-MCP`, PHP aqui) — motivo histórico (Node antes do SDK oficial PHP existir; PHP depois por conveniência de toolchain único) — e aviso de segurança contra adicionar tools de escrita casualmente em qualquer um dos dois.
- README.md ganha uma linha curta na seção de stack/estrutura apontando pra `docs/MCP.md`.

## Fora do design (decisões operacionais, não técnicas)

- Commit: sim, segue o workflow normal do repo (branch a partir de `main` se necessário, commit local).
- Push: só depois de confirmar a política de push atual do repo com o usuário — não assumir.
