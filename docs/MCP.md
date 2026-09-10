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

Sem token/API acessível, o esperado é `"isError":true` com a mensagem real
(token faltando, 404, erro de conexão) — nunca `-32603 Error while executing tool`,
que significa que a tool nem chegou a rodar. O equivalente automatizado disso é
`tests/Unit/Mcp/McpProtocolRoundTripTest.php`.
