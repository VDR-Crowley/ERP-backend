# Plano de migração das entidades do IndexedDB para o backend

Levantamento feito a partir de: `ERP-front/src/app/core/interfaces/*.ts`, serviços/telas em
`ERP-front/src/app/pages/platform/*`, `ERP-front/src/app/core/idb/idb-seed.service.ts`
(`IDB_STORES`), utils de regra de negócio (`business-line-report.util.ts`,
`hatch-tracking.util.ts`, `stock-location.ts`) e a planilha real `MiniERP_22_08.xlsx` (abas
cruzadas com cada entidade abaixo).

`users` **não entra** — já migrado (Sanctum, 41 testes). `dashboard`, `BusinessLineReport`,
`ProductLineResult`, `ReportResumo` **não são entidades persistidas**: são agregações
calculadas em cima das entidades reais (confirmado lendo os componentes — nenhuma delas lê de
um `IDB_STORES.dashboard` ou equivalente com dado próprio).

## Observação sobre `species` (3 vocabulários distintos no front — preservados como estão)

O front usa três representações diferentes pra "espécie", sem unificação:
- `flock.species`: string livre (rótulo real do usuário, ex. `"Galinhas Embrapa 051"`).
- `flockIncubation.species` / `flockCleaning.species`: enum `'quail' | 'chicken'` (inglês).
- `expenseSpeciesOverride.species` / regra de rateio (`business-line-report.util.ts`): enum
  `'codorna' | 'galinha'` (português).

Mantive essa inconsistência no schema (não é escopo desta etapa unificar) — fica registrada
como decisão pendente no final deste documento.

## Observação sobre relacionamentos "por nome" no front

Hoje `Venda.product` e `Venda.seller` guardam **nome** (string), não id — o front resolve por
busca em array (`products.find(p => p.name === ...)`). Local de estoque
(`stockLocation`/`fromLocation`/`toLocation`) é uma string (`'plantel'` ou `` `vendedor:${id}` ``)
combinando dois conceitos num campo só. No backend uso FK reais (`product_id`, `seller_id`) e
colunas separadas de tipo+id de local — mais correto relacionalmente; a importação de dados
antigos (fora de escopo aqui) vai precisar resolver nome → id.

---

## Entidades mapeadas

### 1. `products` (Produtos)
Fonte: `product.interface.ts`, aba **Produtos**.

| Campo | Tipo | Obrigatório | Observação |
|---|---|---|---|
| name | string | sim | único |
| unit | string | sim | ex. "Bandeja (30 ovos)" |
| unit_price | decimal(10,2) | sim | |
| stock | integer | sim, default 0 | estoque **no Plantel**; estoque por vendedor vive em `vendor_stock` |
| eggs_per_unit | integer | sim, default 0 | usado no rateio por espécie (kits mistos) |

Regra de negócio que fica pra depois: `resolveProductComposition`/`computeEggUnitPrices`
(parse do nome pra achar composição de kit misto).

### 2. `vendedores` (Vendedores/Revendedores)
Fonte: `vendedor.interface.ts`.

| Campo | Tipo | Obrigatório |
|---|---|---|
| name | string | sim |
| contact | string | não |
| active | boolean | sim, default true |

### 3. `flock` (Plantel — headcount atual por linha)
Fonte: `plantel.interface.ts`, aba **Plantel**.

| Campo | Tipo | Obrigatório |
|---|---|---|
| species | string | sim | rótulo livre, ex. "Codornas" |
| quantity | integer | sim |
| feed_bags_per_month | integer | sim |
| bag_price | decimal(10,2) | sim |
| monthly_total | decimal(10,2) | sim |

### 4. `flock_incubations` (Novo Lote Plantel / incubação)
Fonte: `novo-lote-plantel.interface.ts`, aba **Novo Plantel**.

| Campo | Tipo | Obrigatório |
|---|---|---|
| start_date | date | sim |
| species | enum('quail','chicken') | sim |
| egg_count | integer | sim |
| expected_hatch_date | date | sim |
| status | enum('incubando','eclodido') | sim, default 'incubando' |
| egg_cost | decimal(10,2) nullable | não |
| feed_cost | decimal(10,2) nullable | não |
| notes | text nullable | não |

Campos legados do front (`actualHatchDate`, `hatchedCount`, pré-`hatchEvents`) **não** entram no
schema novo — o front só os usa pra migrar registro antigo do próprio IndexedDB pra
`hatch_events`; no backend `hatch_events` já nasce como fonte única.

### 5. `hatch_events` (histórico de nascimentos de um lote — filho de `flock_incubations`)
Fonte: `HatchEvent` em `novo-lote-plantel.interface.ts`.

| Campo | Tipo | Obrigatório |
|---|---|---|
| flock_incubation_id | FK → flock_incubations | sim |
| date | date | sim |
| count | integer | sim |
| notes | string nullable | não |

Regra que fica pra depois: `deriveStatusAfterHatchChange` (fechar lote automaticamente quando
soma dos eventos atinge `egg_count`).

### 6. `vendor_stock` (Estoque por Vendedor)
Fonte: `vendor-stock.interface.ts`.

| Campo | Tipo | Obrigatório |
|---|---|---|
| product_id | FK → products | sim |
| vendedor_id | FK → vendedores | sim |
| quantity | integer | sim, default 0 |

Constraint: único em (`product_id`, `vendedor_id`).

### 7. `sales` (Vendas)
Fonte: `venda.interface.ts`, aba **Vendas**.

| Campo | Tipo | Obrigatório |
|---|---|---|
| date | date | sim |
| product_id | FK → products | sim |
| quantity | integer | sim |
| unit_price | decimal(10,2) | sim |
| total | decimal(10,2) | sim |
| payment_pending | boolean | sim |
| buyer | string | sim | texto livre (não é `vendedores`) |
| seller_id | FK → vendedores | sim | campo "Vendedor" da planilha, hoje autocomplete por nome |
| delivery_pending | boolean | sim |
| delivery_date | date nullable | não |
| stock_location_type | enum('plantel','vendedor') | sim, default 'plantel' |
| stock_location_vendedor_id | FK → vendedores, nullable | não | preenchido só quando `stock_location_type = 'vendedor'` |

Regra que fica pra depois: baixa de estoque no local certo ao salvar (`adjustStock`).

### 8. `sale_exclusions` (venda marcada como evento isolado)
Fonte: `sale-exclusion.interface.ts`.

| Campo | Tipo | Obrigatório |
|---|---|---|
| sale_id | FK → sales, único | sim |
| reason | text | sim |

Sem `updated_at` (só marca/desmarca — modelo com `UPDATED_AT = null`, mesmo padrão de
`PasswordResetToken`).

### 9. `stock_transfers` (Transferência de estoque entre locais)
Fonte: `stock-transfer.interface.ts`.

| Campo | Tipo | Obrigatório |
|---|---|---|
| date | date | sim |
| product_id | FK → products | sim |
| quantity | integer | sim |
| from_location_type | enum('plantel','vendedor') | sim |
| from_vendedor_id | FK → vendedores, nullable | não |
| to_location_type | enum('plantel','vendedor') | sim |
| to_vendedor_id | FK → vendedores, nullable | não |
| note | text nullable | não |

### 10. `daily_productions` (Produção diária)
Fonte: `producao-diaria.interface.ts`, aba **Produção**.

| Campo | Tipo | Obrigatório |
|---|---|---|
| date | date, único | sim |
| quail_eggs | integer nullable | não |
| chicken_eggs | integer nullable | não |

### 11. `egg_stocks` (Estoque de ovos)
Fonte: `estoque-ovos.interface.ts`, aba **Estoque de Ovos**.

| Campo | Tipo | Obrigatório |
|---|---|---|
| date | date, único | sim |
| quail_eggs | integer nullable | não |
| chicken_eggs | integer nullable | não |
| quail_packs | decimal(10,2) | sim |
| chicken_packs | decimal(10,2) | sim |
| quail_stock_value | decimal(10,2) | sim |
| chicken_stock_value | decimal(10,2) | sim |

### 12. `expenses` (Despesas)
Fonte: `expense.interface.ts`, aba **Despesas**.

| Campo | Tipo | Obrigatório |
|---|---|---|
| date | date | sim |
| description | string | sim |
| category | string | sim |
| quantity | integer nullable | não |
| unit_price | decimal(10,2) nullable | não |
| amount | decimal(10,2) | sim |
| paid | boolean | sim |

### 13. `expense_species_overrides` (override manual de espécie por despesa)
Fonte: `expense-species-override.interface.ts`.

| Campo | Tipo | Obrigatório |
|---|---|---|
| expense_id | FK → expenses, único | sim |
| species | enum('codorna','galinha') nullable | não | `null` = força rateio pelo plantel |
| reason | text | sim |

Sem `updated_at` (`UPDATED_AT = null`, mesmo padrão de `sale_exclusions`).

### 14. `cash_flows` (Fluxo de Caixa)
Fonte: `cash-entry.interface.ts`, aba **Fluxo de Caixa** (vazia na planilha real, mas schema
confirmado pelo código).

| Campo | Tipo | Obrigatório |
|---|---|---|
| date | date | sim |
| description | string | sim |
| inflow | boolean | sim |
| amount | decimal(10,2) | sim |

Lançamento manual, independente de `sales`/`expenses` (não é auto-gerado).

### 15. `feed_stocks` (Estoque de ração por tipo)
Fonte: `feed-stock.interface.ts` (`FeedStock`), aba **Ração**.

| Campo | Tipo | Obrigatório |
|---|---|---|
| type | string, único | sim |
| bags_in_stock | integer | sim |
| kg_in_stock | decimal(10,2) | sim |
| last_bag_weight_kg | decimal(8,2) | sim |
| expiration_date | date nullable | não |

### 16. `feed_open_logs` (Log de abertura de saco de ração)
Fonte: `feed-stock.interface.ts` (`FeedOpenLog`), aba **Ração - Sacos Abertos**.

| Campo | Tipo | Obrigatório |
|---|---|---|
| feed_stock_id | FK → feed_stocks, nullable | não | resolvido por `type` ao registrar; nullable pra não travar log se o tipo não bater com nenhum estoque cadastrado |
| feed_type | string | sim | mantido como no front (texto solto), redundante com a FK de propósito — mostra o tipo mesmo se a FK for nula |
| date | date | sim |
| weight_kg | decimal(8,2) | sim |

### 17. `flock_cleanings` (Higienização)
Fonte: `flock-cleaning.interface.ts`, aba **Higienização**.

| Campo | Tipo | Obrigatório |
|---|---|---|
| date | date | sim |
| species | enum('quail','chicken') | sim |
| cleaning_type | enum('total','feeder','tray','nest') | sim |
| notes | text nullable | não |

---

## Relacionamentos (FKs)

```
products ─┬─< sales
          ├─< vendor_stock >─┐
          └─< stock_transfers│
                              │
vendedores ─┬─< sales.seller_id
            ├─< sales.stock_location_vendedor_id (nullable)
            ├─< vendor_stock
            └─< stock_transfers.from_vendedor_id / to_vendedor_id (nullable)

sales ──< sale_exclusions (1:1 por venda, via unique)

expenses ──< expense_species_overrides (1:1 por despesa, via unique)

flock_incubations ──< hatch_events

feed_stocks ──< feed_open_logs (nullable)

flock, daily_productions, egg_stocks, cash_flows, flock_cleanings: sem FK (entidades soltas)
```

## Ordem de dependência das migrations

1. `products`
2. `vendedores`
3. `flock`
4. `flock_incubations`
5. `hatch_events` (depende de 4)
6. `vendor_stock` (depende de 1, 2)
7. `sales` (depende de 1, 2)
8. `sale_exclusions` (depende de 7)
9. `stock_transfers` (depende de 1, 2)
10. `daily_productions`
11. `egg_stocks`
12. `expenses`
13. `expense_species_overrides` (depende de 12)
14. `cash_flows`
15. `feed_stocks`
16. `feed_open_logs` (depende de 15)
17. `flock_cleanings`

## Rotas propostas

Todas dentro do grupo já existente `middleware(['auth:sanctum', 'abilities:access'])` em
`routes/api.php` (mesmo grupo de `/user` e `/logout`). Padrão RESTful (`apiResource`), mais
rotas especiais pra ações que hoje existem nas telas. **Nesta etapa os controllers são finos e
as ações especiais ficam como esqueleto (stub, sem lógica de negócio)** — implementação real
fica pra próxima etapa.

```
apiResource('products', ProductController::class)
apiResource('vendedores', VendedorController::class)
apiResource('flock', FlockController::class)

apiResource('flock-incubations', FlockIncubationController::class)
  GET    /flock-incubations/{flockIncubation}/hatch-events
  POST   /flock-incubations/{flockIncubation}/hatch-events
  PUT    /flock-incubations/{flockIncubation}/hatch-events/{hatchEvent}
  DELETE /flock-incubations/{flockIncubation}/hatch-events/{hatchEvent}

apiResource('vendor-stock', VendorStockController::class)

apiResource('sales', SaleController::class)
  POST   /sales/{sale}/exclusion   (marca como evento isolado)
  DELETE /sales/{sale}/exclusion   (desmarca)

apiResource('stock-transfers', StockTransferController::class)
apiResource('daily-productions', DailyProductionController::class)
apiResource('egg-stocks', EggStockController::class)

apiResource('expenses', ExpenseController::class)
  POST   /expenses/{expense}/species-override
  DELETE /expenses/{expense}/species-override

apiResource('cash-flows', CashFlowController::class)

apiResource('feed-stocks', FeedStockController::class)
  POST   /feed-stocks/{feedStock}/replenish   (repor sacos)
  POST   /feed-stocks/{feedStock}/open-bag    (abrir saco -> gera feed_open_log)

GET     /feed-open-logs   (somente leitura — criado via /open-bag)

apiResource('flock-cleanings', FlockCleaningController::class)
```

---

## Decisões pendentes (preciso de confirmação antes de seguir pra lógica de negócio)

1. **Unificar vocabulário de espécie?** Hoje são 3: `flock.species` (texto livre),
   `quail/chicken` (`flock_incubations`, `flock_cleanings`), `codorna/galinha`
   (`expense_species_overrides`, rateio). Mantive como está — decidir se unifica num enum só
   antes de implementar a lógica de rateio no backend.
2. **`sales.seller_id`**: hoje é obrigatório (front exige "Vendedor" na venda). Confirma que
   toda venda tem vendedor, ou existe caso de venda direta sem vendedor cadastrado?
3. **`feed_open_logs.feed_stock_id` nullable + `feed_type` redundante**: aceito esse desenho
   (tolera log "órfão" se o tipo não bater com nenhum estoque) ou prefere obrigar a FK e
   remover `feed_type`?
4. **Import de dados antigos** (Excel/IndexedDB → banco novo): não faz parte desta etapa. Vai
   precisar resolver `product`/`seller` por nome → id. Confirmar se entra numa etapa própria.
5. **API Resources e Form Requests completos**: criei Form Requests básicos (tipo/obrigatoriedade)
   pra `store`/`update` de cada entidade, mas sem regra de negócio (ex.: não valido ainda que
   `stock_location_vendedor_id` exista quando `stock_location_type = 'vendedor'`). Fica pra
   quando a lógica de negócio entrar.
