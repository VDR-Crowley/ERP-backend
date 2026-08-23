# Deploy no Railway

Arquitetura "majestic monolith": 1 codebase, 3 services no Railway (App, Cron,
Worker) + 1 plugin Postgres. Cada service builda a mesma imagem, mas roda um
comando diferente.

> Status: PR #1 já mergeado em `main`. O Railway builda a partir da
> branch/commit configurado no projeto.

## 0. Pré-requisito

Conta no Railway + projeto criado (`railway init` ou pelo dashboard). Isso é
manual, feito pelo usuário — não faz parte deste preparo de repositório.

## 1. Plugin Postgres

No dashboard do projeto Railway: **New → Database → PostgreSQL**. O Railway
cria a variável `DATABASE_URL` nesse plugin, referenciável por outros
services como `${{Postgres.DATABASE_URL}}`.

## 2. App Service

Service principal, serve a API.

- **Build Command**: padrão do Nixpacks (detecta Laravel via `composer.json`,
  roda `composer install`).
- **Pre-Deploy Command**: `bash railway/init-app.sh`
  Roda migrations, limpa e recria os caches (config/event/route/view) antes
  do deploy trocar de versão.
- **Custom Start Command**: deixar o padrão do Nixpacks para Laravel (serve
  via `php artisan serve` ou o servidor web que o Nixpacks provisionar) —
  não precisa de script próprio pra isso.
- **Domínio público**: em **Settings → Networking → Generate Domain**. Só
  esse service precisa de domínio público; Cron e Worker não recebem tráfego
  HTTP.

### 2.1 Versão do PHP no build

O Nixpacks (builder padrão do Railway) detecta a versão do PHP lendo o campo
`require.php` do `composer.json` — não existe variável de ambiente
documentada pra isso (não é `NIXPACKS_PHP_VERSION` nem similar; se aparecer
em algum lugar, não é mecanismo oficial do Nixpacks). O Nixpacks só
disponibiliza os pacotes nix `php81`, `php82` (default), `php83` e `php84` —
não existe `php85`.

Hoje o `composer.json` declara `"php": "^8.4"`, exigido de verdade pelo
`symfony/http-foundation` (e outros pacotes do Laravel 13) que pedem
`>=8.4.1`. Isso já resolve o build sozinho — não precisa de `nixpacks.toml`
nem de variável de ambiente manual no painel do Railway.

Não confundir com o ambiente local: `composer.json` pode ficar em `^8.4` e
seguir funcionando com o PHP 8.5.x local (Homebrew), já que `^8.4` aceita
qualquer 8.4.x/8.5.x/etc. até a próxima major. Só o Nixpacks (que não tem
`php85` disponível) fica travado no maior compatível, `php84`.

## 3. Cron Service

Roda o scheduler do Laravel (`php artisan schedule:run`) a cada minuto.

- Mesmo repositório/branch do App Service.
- **Build Command**: padrão (mesma imagem).
- **Custom Start Command**: `bash railway/run-cron.sh`
- Sem domínio público.
- Hoje `routes/console.php` não tem nenhum `Schedule::` registrado — o
  service fica pronto e ocioso até o projeto precisar de alguma tarefa
  agendada (ex.: limpeza de tokens expirados, relatórios).

## 4. Worker Service

Processa a queue (`QUEUE_CONNECTION=database`, tabelas `jobs`/`failed_jobs`
já existem via migration `0001_01_01_000002_create_jobs_table.php`).

- Mesmo repositório/branch.
- **Build Command**: padrão.
- **Custom Start Command**: `bash railway/run-worker.sh`
- Sem domínio público.

## 5. Variáveis de ambiente (todos os 3 services)

Baseado no `.env.example` real do projeto:

| Variável | Valor em produção | Observação |
|---|---|---|
| `APP_NAME` | `MiniERP` | |
| `APP_ENV` | `production` | |
| `APP_KEY` | gerar com `php artisan key:generate --show` | copiar o valor gerado, não rodar o comando direto no Railway |
| `APP_DEBUG` | `false` | nunca `true` em produção |
| `APP_URL` | domínio público gerado no App Service | ex. `https://erp-backend-production.up.railway.app` |
| `DB_CONNECTION` | `pgsql` | |
| `DB_URL` | `${{Postgres.DATABASE_URL}}` | referência ao plugin Postgres |
| `QUEUE_CONNECTION` | `database` | já é o padrão do projeto |
| `LOG_CHANNEL` | `stderr` | filesystem do Railway é efêmero, log em arquivo se perde |
| `LOG_STDERR_FORMATTER` | `\Monolog\Formatter\JsonFormatter` | log estruturado em JSON no stderr |
| `CORS_ALLOWED_ORIGINS` | domínio real do ERP-front em produção | hoje aponta pro dev local (`http://localhost:4200,http://localhost:4201`); trocar quando o front também for hospedado |

Todas as demais variáveis do `.env.example` (`SESSION_*`, `CACHE_STORE`,
`MAIL_*`, `BCRYPT_ROUNDS`, etc.) podem manter os defaults ou serem ajustadas
conforme a necessidade, sem relação direta com a infra do Railway.

O projeto não usa autenticação stateful de SPA via Sanctum (só
`auth:sanctum` com tokens/abilities em `routes/api.php`), então
`SANCTUM_STATEFUL_DOMAINS` não é necessário.

## 6. Ordem de setup recomendada

1. Criar o plugin Postgres.
2. Criar o App Service, apontando pro repositório/branch.
3. Configurar as variáveis de ambiente do App Service (tabela acima).
4. Configurar Pre-Deploy Command (`railway/init-app.sh`) no App Service e
   fazer o primeiro deploy — isso roda a migration inicial no Postgres.
5. Gerar o domínio público do App Service.
6. Criar o Cron Service e o Worker Service apontando pro mesmo
   repositório/branch, com os Custom Start Commands respectivos
   (`railway/run-cron.sh` e `railway/run-worker.sh`) e as mesmas variáveis
   de ambiente (especialmente `DB_URL`, `APP_KEY`, `QUEUE_CONNECTION`).

## 7. Scripts criados

- `railway/init-app.sh` — Pre-Deploy do App Service: migrate + rebuild de
  caches.
- `railway/run-worker.sh` — Start Command do Worker Service: `queue:work`.
- `railway/run-cron.sh` — Start Command do Cron Service: loop de 60s
  chamando `schedule:run`.

Todos com permissão de execução (`chmod +x`, modo `100755` no git).
