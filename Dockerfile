# syntax=docker/dockerfile:1

# ERP-Backend (Laravel 13, API-only) — imagem única reaproveitada pelos 3
# services do Railway (App/Cron/Worker), variando só o start command.
#
# Decisão de arquitetura: servidor HTTP embutido do Laravel (`php artisan
# serve`), não nginx+php-fpm. Justificativa em docs/deploy-railway.md — em
# resumo: API interna de gestão de uma fazenda pequena, baixo tráfego single
# node, sem SSR/assets estáticos pesados (front é outro app à parte). Um
# único processo PHP embutido é suficiente e elimina a complexidade de
# configurar+manter nginx, pool de php-fpm e supervisor dentro do container.
# Fica documentado como trade-off consciente, não como desconhecimento da
# prática padrão de produção.

FROM php:8.4-cli-bookworm

# Dependências de sistema pras extensões PHP abaixo (gd precisa de libs de
# imagem; zip e postgres precisam de suas dev libs).
RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libpq-dev \
        libonig-dev \
        unzip \
        git \
    && rm -rf /var/lib/apt/lists/*

# install-php-extensions (mlocati) — instala e habilita extensões PHP de
# forma confiável, resolvendo dependências de sistema automaticamente.
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/bin/

# Extensões exigidas pelo composer.json (require ext-*) + pdo_pgsql (Postgres
# em produção) + pdo_sqlite (paridade com dev/testing, que usa sqlite).
# ctype, fileinfo, filter, iconv, tokenizer e session já vêm no core do PHP.
RUN install-php-extensions \
        gd \
        zip \
        mbstring \
        dom \
        simplexml \
        xml \
        xmlreader \
        xmlwriter \
        pdo_pgsql \
        pdo_sqlite \
        opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copia só os manifestos primeiro pra cachear a camada do `composer install`
# entre builds quando só o código da aplicação muda.
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --no-interaction \
        --prefer-dist

# Agora sim o restante do código da aplicação.
COPY . .

# Gera o autoloader otimizado e roda os scripts do composer.json
# (package:discover etc.) já com o código-fonte completo presente.
RUN composer dump-autoload --optimize --no-dev \
    && chmod +x railway/*.sh \
    && chmod -R ug+rwx storage bootstrap/cache

EXPOSE 8080

# Railway injeta $PORT em runtime — o container precisa escutar nela, não
# numa porta fixa. Fica em shell form de propósito pra expandir a variável.
#
# Cache (config/event/route/view) continua só no Pre-Deploy Command do App
# Service (`bash railway/init-app.sh`, documentado em docs/deploy-railway.md)
# — não repetido aqui porque Cron/Worker sobrescrevem esse CMD via Custom
# Start Command (`railway/run-cron.sh` / `railway/run-worker.sh`) e não
# precisam de cache pra rodar.
#
# `migrate --force` AQUI TAMBÉM (redundante com o Pre-Deploy, idempotente —
# migration já rodada é no-op): rede de segurança porque Pre-Deploy Command é
# config manual do dashboard do Railway, fora do repositório/imagem — se
# nunca foi configurado, foi apagado, ou falhou silenciosamente numa troca de
# builder, o App Service subia servindo rotas cujas tabelas não existem
# (`flock_incubations`/`hatch_events` reproduzido localmente: INSERT vira
# `Illuminate\Database\QueryException` não tratada, 500 cru pro front em vez
# de 422 — ver tests/Feature/FlockIncubationImportTest.php). Migrar antes do
# serve garante que o processo que efetivamente atende HTTP nunca sobe com
# schema desatualizado, mesmo que o Pre-Deploy tenha sido pulado.
CMD php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=${PORT:-8080}
