# Deploy no Railway

Arquitetura "majestic monolith": 1 codebase, 3 services no Railway (App, Cron,
Worker) + 1 plugin Postgres. Cada service builda a mesma imagem, mas roda um
comando diferente.

> Status: PR #1 já mergeado em `main`. O Railway builda a partir da
> branch/commit configurado no projeto.

## 0.1 Build: Dockerfile explícito (não Nixpacks puro)

O repositório tem um `Dockerfile` na raiz. O Railway detecta e usa
automaticamente sempre que existe um `Dockerfile` no diretório raiz do
service — não precisa configurar nada no painel pra ativar isso, nem trocar
builder manualmente. Nada do resto deste documento muda por causa disso: os
3 services continuam apontando pro mesmo repositório/branch, só que agora
buildam a partir do `Dockerfile` em vez da autodetecção Nixpacks.

Motivo da troca: a autodetecção Nixpacks já causou dois erros de build
diferentes neste projeto (nome de extensão inválido e versão de PHP
incompatível — ambos corrigidos, ver seção 2.1/2.2 abaixo, mantida como
histórico). Um Dockerfile explícito dá controle direto sobre versão de PHP,
extensões instaladas e imagem base, sem depender de heurística de
autodetecção lendo `composer.json`.

**Decisão de servidor HTTP: `php artisan serve`, não nginx+php-fpm.** Prática
padrão de produção Laravel é nginx (ou outro reverse proxy) na frente de
php-fpm, por concorrência (múltiplos workers FPM) e por servir assets
estáticos direto pelo webserver. Esse projeto é uma ferramenta interna de
gestão de uma fazenda pequena: API-only (sem Blade/assets — o front é outro
app com deploy próprio), baixo tráfego, um único node por service. Rodar
`php artisan serve` embutido dentro do container elimina a necessidade de
configurar e manter nginx.conf, pool de php-fpm e um supervisor pra
gerenciar os dois processos — um único processo PHP resolve, e é isso que o
`Dockerfile` faz. Se o tráfego crescer a ponto de `php artisan serve` virar
gargalo (é single-threaded), migrar pra php-fpm+nginx (ou Swoole/RoadRunner)
é um passo natural futuro, não uma correção de um erro atual.

O `Dockerfile`:

- Base `php:8.4-cli-bookworm`.
- Extensões instaladas via `install-php-extensions` (mlocati): as mesmas
  `ext-*` do `composer.json` (seção 2.2) + `pdo_pgsql` (Postgres de
  produção) + `pdo_sqlite` (paridade com dev/testing, que usa sqlite) +
  `opcache`. As demais (`ctype`, `fileinfo`, `filter`, `iconv`, `tokenizer`,
  `session`, `json`) já vêm habilitadas por padrão na imagem oficial do PHP.
- `composer install --no-dev --optimize-autoloader` (em duas etapas: sem
  scripts antes de copiar o código-fonte, pra cachear a camada; depois
  `composer dump-autoload` com o código já presente).
- `EXPOSE 8080` só como documentação — o `CMD` de fato escuta em `$PORT`
  (variável que o Railway injeta em runtime), não numa porta fixa:
  `php artisan serve --host=0.0.0.0 --port=${PORT:-8080}`.
- **Migration + cache continuam fora do `CMD`**, no Pre-Deploy Command do App
  Service (seção 2 abaixo) — isso é um recurso do Railway independente do
  builder (funciona igual com Dockerfile ou Nixpacks), então nada muda no
  fluxo de deploy documentado neste arquivo.

## 0. Pré-requisito

Conta no Railway + projeto criado (`railway init` ou pelo dashboard). Isso é
manual, feito pelo usuário — não faz parte deste preparo de repositório.

## 1. Plugin Postgres

No dashboard do projeto Railway: **New → Database → PostgreSQL**. O Railway
cria a variável `DATABASE_URL` nesse plugin, referenciável por outros
services como `${{Postgres.DATABASE_URL}}`.

## 2. App Service

Service principal, serve a API.

- **Build**: automático via `Dockerfile` da raiz (ver seção 0.1) — Railway
  detecta sozinho, não precisa selecionar builder no painel.
- **Pre-Deploy Command**: `bash railway/init-app.sh`
  Roda migrations, limpa e recria os caches (config/event/route/view) antes
  do deploy trocar de versão.
- **Custom Start Command**: deixar em branco — usa o `CMD` do `Dockerfile`
  (`php artisan serve --host=0.0.0.0 --port=${PORT:-8080}`).
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

### 2.2 Extensões PHP no build

O mesmo mecanismo do `require.php` vale pra extensões: o Nixpacks lê chaves
`ext-*` do `composer.json` e provisiona a extensão correspondente no build.
Se um pacote precisa de uma extensão (ex.: `phpoffice/phpspreadsheet` exige
`ext-gd`) e ela não está declarada em `require`, o Nixpacks não sabe que
precisa instalar e o build quebra com `ext-gd ... it is missing from your
system`.

Hoje `composer.json` declara todas as extensões que o
`phpoffice/phpspreadsheet` exige de verdade (`ctype`, `dom`, `fileinfo`,
`filter`, `gd`, `iconv`, `libxml`, `mbstring`, `simplexml`, `xml`,
`xmlreader`, `xmlwriter`, `zip`, `zlib` — ver `composer show
phpoffice/phpspreadsheet` pra lista completa de um pacote). Foi declarada
uma de cada vez em builds anteriores (`gd`, depois `zip`) até declarar o
conjunto inteiro de uma vez — pra evitar esse loop com um pacote novo,
checar `composer show <pacote> | grep ext-` e declarar tudo que aparecer,
não só a extensão do erro atual.

## 3. Cron Service

Roda o scheduler do Laravel (`php artisan schedule:run`) a cada minuto.

- Mesmo repositório/branch do App Service — builda a **mesma imagem Docker**
  (mesmo `Dockerfile`), só troca o Custom Start Command.
- **Build**: automático via `Dockerfile` (mesma imagem do App Service).
- **Custom Start Command**: `bash railway/run-cron.sh`
  Isso sobrescreve o `CMD` do `Dockerfile` por completo — o Railway roda
  literalmente esse comando dentro do container em vez do `php artisan
  serve` padrão. É o mesmo mecanismo de Custom Start Command que já existia
  com Nixpacks; funciona igual com Dockerfile.
- Sem domínio público.
- Hoje `routes/console.php` não tem nenhum `Schedule::` registrado — o
  service fica pronto e ocioso até o projeto precisar de alguma tarefa
  agendada (ex.: limpeza de tokens expirados, relatórios).

## 4. Worker Service

Processa a queue (`QUEUE_CONNECTION=database`, tabelas `jobs`/`failed_jobs`
já existem via migration `0001_01_01_000002_create_jobs_table.php`).

- Mesmo repositório/branch — mesma imagem Docker do App Service.
- **Build**: automático via `Dockerfile` (mesma imagem do App Service).
- **Custom Start Command**: `bash railway/run-worker.sh`
  Mesmo mecanismo de override do `CMD` descrito na seção do Cron Service.
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

## 7. Scripts e arquivos criados

- `Dockerfile` — build da imagem única reaproveitada pelos 3 services (ver
  seção 0.1). `php artisan serve` como servidor HTTP, extensões PHP
  necessárias instaladas explicitamente, escuta em `$PORT`.
- `.dockerignore` — evita copiar `vendor/`, `.env`, `.git`, `storage/logs`,
  `node_modules` etc. pro contexto de build.
- `railway/init-app.sh` — Pre-Deploy do App Service: migrate + rebuild de
  caches.
- `railway/run-worker.sh` — Start Command do Worker Service: `queue:work`.
- `railway/run-cron.sh` — Start Command do Cron Service: loop de 60s
  chamando `schedule:run`.

Os três scripts com permissão de execução (`chmod +x`, modo `100755` no
git).
