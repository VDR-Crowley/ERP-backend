# Servidor MCP (PHP)

Servidor [MCP](https://modelcontextprotocol.io) de leitura, em PHP, vivendo dentro deste próprio backend (`ERP-Backend`). Expõe a API do MiniERP como tools tipadas pra um assistente de IA.

## Por que existe

Já existe um servidor MCP equivalente em Node/TypeScript, `../ERP-MCP` — feito antes de existir um SDK oficial de MCP em PHP. Agora que existe (`mcp/sdk`, mantido por Symfony + PHP Foundation), faz sentido ter a versão nativa no mesmo toolchain do backend (PHP/Laravel), pelo mesmo motivo original do Node: acesso rápido e seguro de leitura a dados de produção, sem escrever `tinker` na mão pra cada pergunta, e sem depender de um segundo toolchain (Node) só pra isso.

**Ver [nota de segurança](#dois-servidores-mcp-pra-este-backend) no fim — leia antes de mexer em qualquer um dos dois.**

### ADR: leitura direta via Eloquent, não HTTP na própria API

Versão original deste servidor (porte 1:1 do Node) fazia cada tool chamar a própria API via HTTP (`Illuminate\Support\Facades\Http`), autenticada com um token Sanctum de usuário real (`ERP_API_TOKEN`, expira em 2h). Isso fazia sentido no Node — processo separado, sem acesso ao Eloquent — mas nunca fez sentido aqui: o servidor PHP roda dentro do MESMO processo/deploy Laravel, com a mesma conexão de banco já disponível. O round-trip HTTP era indireção pura, herdada do porte sem repensar, e criava uma dependência real: automação (o próprio propósito do MCP) ficava presa a um humano logar de novo a cada 2h.

Fix: `App\Mcp\ErpDataReader` lê direto via Eloquent (mesmos Models/Services que os Controllers da API usam), sem HTTP, sem `ERP_API_TOKEN`. `ErpApiClient` foi removido. O gate de acesso que continua sendo o real — quem pode falar com o servidor MCP — é o `MCP_HTTP_TOKEN` (transporte HTTP) ou o processo local (STDIO); nenhum dos dois nunca dependeu do Sanctum de usuário pra decidir isso.

## Instalar

```bash
composer install
```

`mcp/sdk` já é uma dependência do projeto (`composer.json`) — nada a mais pra instalar.

## Configurar

Nada a configurar pro STDIO local: as tools leem direto do banco que o próprio `.env` deste projeto já aponta (mesma `DB_CONNECTION` de sempre). Não existe mais `ERP_API_TOKEN`/`ERP_API_BASE_URL` — ver [ADR acima](#adr-leitura-direta-via-eloquent-não-http-na-própria-api).

Só o transporte HTTP (produção) precisa de config — `MCP_HTTP_TOKEN`, ver a seção "Transporte HTTP" abaixo.

## Registrar no Claude Code / Claude Desktop

**Claude Code (STDIO local):**

```bash
claude mcp add erp-mcp-php -- php /caminho/absoluto/para/ERP-Backend/artisan mcp:serve
```

Roda contra o banco que o `.env` local do projeto aponta — sem env var adicional. Pra ler dado de produção, use o transporte HTTP abaixo (ele já roda no deploy real, com o banco real) em vez de apontar o STDIO local pra produção.

**Config JSON cru de cliente MCP:**

```json
{
  "mcpServers": {
    "erp-mcp-php": {
      "command": "php",
      "args": ["/caminho/absoluto/para/ERP-Backend/artisan", "mcp:serve"]
    }
  }
}
```

## Transporte HTTP (produção, sem STDIO local)

Além do STDIO (`php artisan mcp:serve`, rodado localmente como subprocesso), este servidor também responde ao protocolo MCP via HTTP, direto no mesmo deploy Railway já existente — sem subir serviço novo. Rota: `POST /api/mcp`.

Use isso quando quiser apontar um cliente MCP direto pra produção, sem rodar nada localmente.

### Autenticação

Token dedicado — quem pode falar com o servidor MCP (não é Sanctum de usuário, não precisa mais de um `ERP_API_TOKEN` interno, ver ADR acima). Header `Authorization: Bearer <MCP_HTTP_TOKEN>`.

Gerar um token novo:

```bash
openssl rand -hex 32
```

Definir no Railway: no serviço do backend, aba de variáveis de ambiente, adicionar `MCP_HTTP_TOKEN` com o valor gerado. Não precisa de redeploy de código — só a env var.

**Aviso de segurança**: essa rota fica exposta no mesmo domínio público do Railway. Trate o `MCP_HTTP_TOKEN` como segredo (não commitar, não logar). Se vazar, gere um novo com o comando acima e troque a env var no Railway — isso invalida o antigo imediatamente, sem precisar de nenhuma outra ação.

### Rate limit

30 requisições/minuto por IP (`throttle:mcp`, ver `app/Providers/AppServiceProvider.php`). Read-only, mas ainda consulta dado real — o limite existe pra não virar vetor de abuso.

**Na prática, hoje, esse limite é um orçamento único do endpoint inteiro, não por chamador.** Atrás do proxy de borda do Railway e sem trusted proxies configurados (`bootstrap/app.php`), `$request->ip()` resolve pro endereço do proxy pra todo mundo — ou seja, todos os clientes dividem o mesmo balde de 30/min. Mesma situação dos limiters `login` e `password-reset`, que são anteriores a isto. Corrigir de verdade exige configurar trusted proxies com o escopo certo (`at: '*'` deixaria o `X-Forwarded-For` ser forjado de fora, o que é pior que o problema atual) — mudança de infra, deliberada e revisada à parte.

### Registrar no Claude Code / Claude Desktop (via HTTP)

```json
{
  "mcpServers": {
    "erp-mcp-php-http": {
      "type": "http",
      "url": "https://laravel-production-4c67.up.railway.app/api/mcp",
      "headers": {
        "Authorization": "Bearer <seu-MCP_HTTP_TOKEN-aqui>"
      }
    }
  }
}
```

O `"type": "http"` não é opcional: é ele que diz ao cliente que esta entrada é um servidor remoto por URL, e não o formato `"command"`/`"args"` usado pelo STDIO acima. Sem ele, cliente nenhum sabe o que fazer com a entrada.

(O resto do formato varia por cliente MCP — confira a documentação do seu cliente pro campo exato de headers customizados; alguns aceitam `headers` direto na entrada do servidor, outros pedem uma flag de linha de comando equivalente.)

## Tools disponíveis

Uma tool por endpoint `GET` de `docs/openapi.yaml` — mesmo escopo do servidor Node:

| Tool | Descrição |
|---|---|
| `get_current_user` | **Não suportada** — não existe usuário autenticado nesta leitura direta via Eloquent. Sempre recusa com `isError: true` explicando isso; registrada só por paridade de nomes com o servidor Node. Use `list_users`. |
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

- `App\Mcp\ErpDataReader` só faz leitura (`all()`/`find*()`/queries `->get()`) — nenhum `create`/`update`/`delete` em nenhum braço do `match`.
- Nenhuma tool de escrita é registrada — só as 29 leituras de `ToolDefinitions` viram tool.
- Se um dia quiser tools de escrita, isso deve ser uma adição deliberada e cuidadosamente escopada (confirmação explícita, capability flag), nunca só relaxar a guarda do reader.

## Dois servidores MCP pra este backend

Hoje existem **dois** servidores MCP equivalentes pra este backend:

- **Node** (`../ERP-MCP`) — feito primeiro, antes de existir um SDK oficial de MCP em PHP.
- **PHP** (aqui, `app/Mcp/` + `php artisan mcp:serve`) — adicionado depois, por conveniência de ter um único toolchain (PHP) pra manter.

(O servidor PHP em si também tem dois *transportes* — STDIO local e HTTP em produção, ver a seção "Transporte HTTP" acima — isso é ortogonal à distinção Node/PHP: são dois eixos diferentes.)

Os dois devem continuar em paridade de escopo (mesmas tools, mesmo comportamento read-only) até decisão em contrário. **Não adicione tools de escrita casualmente em nenhum dos dois** — nem "só uma tool de escrita simples" — sem revisar deliberadamente as implicações de segurança (confirmação explícita, capability flag, revisão separada).

## Desenvolvimento

```bash
php artisan test tests/Unit/Mcp   # só os testes deste servidor
composer test                     # suíte inteira do backend
php artisan mcp:serve             # roda localmente, contra o banco do .env local — sem token de API
```

### Smoke test manual do protocolo

Testar só `initialize` não prova nada: já houve um bug em que todo `tools/call`
morria com `-32603` enquanto a suíte inteira ficava verde. Sempre exercite um
`tools/call` de verdade — um sem argumentos e um com path param:

```bash
printf '%s\n' \
  '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"manual","version":"1.0"}}}' \
  '{"jsonrpc":"2.0","method":"notifications/initialized"}' \
  '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"list_sales","arguments":{}}}' \
  '{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"get_sale","arguments":{"sale":42}}}' \
  | php artisan mcp:serve
```

Se o registro não existir no banco local (`sale:42` num `.env` local sem esse
dado), o esperado é `"isError":true` com a mensagem real do Eloquent (ex. "No
query results for model...") — nunca `-32603 Error while executing tool`, que
significa que a tool nem chegou a rodar. O equivalente automatizado disso é
`tests/Unit/Mcp/McpProtocolRoundTripTest.php`.
