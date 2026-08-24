<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Trava contra reimportação duplicada de vendedor: bug real de produção
 * (2026-08-24), diferente do bug de `sales` (commit f6ee563) — aqui a aba
 * "Vendedores" foi recriada do zero em vez de dar upsert, gerando 2 linhas
 * pro mesmo vendedor (`vendedores.name` nunca teve índice único, diferente
 * de `products.name`). Cada venda que referenciava a cópia "nova" ficou com
 * `seller_id` diferente da cópia "antiga" do mesmo vendedor de negócio —
 * escondendo 117 vendas duplicadas de `sales:deduplicate` (agrupa por
 * `seller_id` cru, que genuinamente divergia mesmo com nome idêntico).
 *
 * Índice funcional (`lower(trim(name))`) em vez de unique simples: a mesma
 * classe de bug que criou "Ytallo" duas vezes poderia criar
 * "Ytallo"/" ytallo " — case e espaço variam entre exports de planilha, um
 * unique comum em `name` não pegaria isso. `lower()`/`trim()` funcionam
 * tanto em SQLite (testes) quanto Postgres (produção), então a mesma
 * instrução SQL serve pros dois.
 *
 * DEPLOY SÓ DEPOIS de `vendedores:merge-duplicates --force` já ter rodado
 * com sucesso em produção — `vendedores` em produção ainda tem os 6
 * vendedores duplicados desse bug até esse comando limpar; se essa
 * migration subir antes, `migrate --force` falha criando o índice sobre
 * dado duplicado e o boot inteiro do App Service cai (`Dockerfile`:
 * `migrate --force && artisan serve`). Mesma lógica de sequenciamento da
 * migration `add_duplicate_unique_index_to_sales_table`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('create unique index vendedores_name_normalized_unique on vendedores (lower(trim(name)))');
    }

    public function down(): void
    {
        DB::statement('drop index vendedores_name_normalized_unique');
    }
};
