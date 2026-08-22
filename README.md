# MiniERP - Backend

API REST em Laravel para o MiniERP (gestão de granja de codorna/galinha). Consumida pelo frontend Angular em `../ERP-front` (repositório irmão, separado).

## Stack

- **Laravel 13** (13.26.1) — PHP 8.3+ requerido
- **PHP 8.5.9** (via Homebrew) / **Composer 2.10.2**
- **Laravel Sanctum** — autenticação de API (tokens)
- **SQLite** — banco de dados de desenvolvimento (zero-config)

## Setup local

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite   # se não existir
php artisan migrate
php artisan serve
```

API sobe em `http://localhost:8000`. Health check: `GET /up`.

## Estrutura de rotas

- `routes/api.php` — todas as rotas da API (prefixo automático `/api`, middleware `api`)
- `routes/web.php` — apenas rota raiz de status (`/`), sem views/Blade — este backend é API-only
- `routes/console.php` — comandos artisan agendados

## Autenticação (Sanctum)

Instalado via `php artisan install:api`. Model `App\Models\User` já tem a trait `Laravel\Sanctum\HasApiTokens`.

Fluxo de token (API pura, sem SPA cookie-auth): login gera um personal access token, cliente Angular manda `Authorization: Bearer <token>` nas próximas requisições. Rotas protegidas usam middleware `auth:sanctum` (ver exemplo em `routes/api.php`).

## CORS

`config/cors.php` publicado explicitamente (não vem no skeleton padrão do Laravel 11+). Origens liberadas via env `CORS_ALLOWED_ORIGINS` (lista separada por vírgula), default:

```
CORS_ALLOWED_ORIGINS=http://localhost:4200,http://localhost:4201
```

`supports_credentials` está `true` (necessário se o front usar cookies/Sanctum SPA no futuro). Ajuste a variável de ambiente com o domínio real em produção — não hardcode no `config/cors.php`.

## Estrutura de código / boas práticas

- **Controllers magros**: lógica de negócio não fica no controller. Fica em Service classes.
- `app/Services/` — classes de serviço com a lógica de negócio (ex.: `SalesService`, `FeedStockService`). Controllers injetam o Service via container e delegam.
- `app/Http/Controllers/Api/` — controllers da API (separados de eventuais controllers web).
- `app/Http/Requests/` — Form Requests para validação de entrada (não validar direto no controller).
- `app/Http/Resources/` — API Resources para formatar as respostas JSON (não devolver Model cru).
- `app/Models/` — Eloquent models.

Pastas criadas vazias (com `.gitkeep`) prontas para receber os primeiros arquivos.

## Banco de dados

**Dev**: SQLite (`database/database.sqlite`, não versionado — recriar com `touch` + `migrate`, ver Setup acima).

**Produção (trocar depois)**: editar `.env` de produção para MySQL ou PostgreSQL:

```
DB_CONNECTION=mysql        # ou pgsql
DB_HOST=127.0.0.1
DB_PORT=3306               # 5432 para PostgreSQL
DB_DATABASE=minierp
DB_USERNAME=...
DB_PASSWORD=...
```

Nenhuma mudança de código é necessária — Eloquent/migrations são agnósticos de driver, exceto se algum recurso específico de SQLite for usado (evitar).

## Próximos passos (entidades do domínio)

Migrations ainda não criadas. Entidades mapeadas a partir das stores do frontend Angular (`ERP-front`):

`sales`, `dailyProduction`, `eggStock`, `flock`, `products`, `expenses`, `cashFlow`, `users`, `flockIncubation`, `feedStock`, `feedOpenLog`, `flockCleaning`, `dashboard` (provavelmente agregação/view, não tabela própria).

## Git

Repositório git próprio nesta pasta (`ERP-Backend`), independente do `ERP-front`. Sem remote configurado ainda.
