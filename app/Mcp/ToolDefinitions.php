<?php

namespace App\Mcp;

/**
 * Uma entrada por endpoint GET de ERP-Backend/docs/openapi.yaml — mesmos
 * nomes/descrições/paths do servidor Node em ../ERP-MCP
 * (src/tools/definitions.ts), hand-authored a partir do spec, não codegen
 * cego. queryParams aqui são sempre string opcional de data ISO
 * (YYYY-MM-DD) — hoje só start/end de get_business_line_report; se um dia
 * um query param não-data for adicionado, o schema builder (McpServerFactory)
 * precisa ser estendido, não só este array.
 *
 * IMPORTANTE: só endpoints GET entram aqui. Não adicionar POST/PUT/PATCH/
 * DELETE — este servidor é read-only por design.
 *
 * @phpstan-type PathParamDef array{name: string, description: string}
 * @phpstan-type QueryParamDef array{name: string, description: string}
 * @phpstan-type ToolDefinition array{
 *     name: string,
 *     description: string,
 *     path: string,
 *     pathParams: list<PathParamDef>,
 *     queryParams: list<QueryParamDef>,
 * }
 */
final class ToolDefinitions
{
    /**
     * @return list<ToolDefinition>
     */
    public static function all(): array
    {
        return [
            [
                'name' => 'get_current_user',
                'description' => 'Get the user data for the currently authenticated API token.',
                'path' => '/user',
                'pathParams' => [],
                'queryParams' => [],
            ],
            [
                'name' => 'list_users',
                'description' => 'List all admin-panel users (not paginated). Excludes the public self-registration flow.',
                'path' => '/users',
                'pathParams' => [],
                'queryParams' => [],
            ],
            [
                'name' => 'list_products',
                'description' => 'List all products sold (eggs, packaging, etc.).',
                'path' => '/products',
                'pathParams' => [],
                'queryParams' => [],
            ],
            [
                'name' => 'get_product',
                'description' => 'Get one product by ID.',
                'path' => '/products/{product}',
                'pathParams' => [['name' => 'product', 'description' => 'Product ID']],
                'queryParams' => [],
            ],
            [
                'name' => 'list_vendedores',
                'description' => 'List all vendedores (resellers/salespeople) registered in the system.',
                'path' => '/vendedores',
                'pathParams' => [],
                'queryParams' => [],
            ],
            [
                'name' => 'get_vendedor',
                'description' => 'Get one vendedor (reseller/salesperson) by ID.',
                'path' => '/vendedores/{vendedor}',
                'pathParams' => [['name' => 'vendedor', 'description' => 'Vendedor ID']],
                'queryParams' => [],
            ],
            [
                'name' => 'list_flock',
                'description' => 'List all flock/plantel batches (quail or chicken batches in production).',
                'path' => '/flock',
                'pathParams' => [],
                'queryParams' => [],
            ],
            [
                'name' => 'get_flock',
                'description' => 'Get one flock/plantel batch by ID.',
                'path' => '/flock/{flock}',
                'pathParams' => [['name' => 'flock', 'description' => 'Flock (plantel) batch ID']],
                'queryParams' => [],
            ],
            [
                'name' => 'list_flock_incubations',
                'description' => 'List all incubation batches, each including its hatch events.',
                'path' => '/flock-incubations',
                'pathParams' => [],
                'queryParams' => [],
            ],
            [
                'name' => 'get_flock_incubation',
                'description' => 'Get one incubation batch by ID, including its hatch events.',
                'path' => '/flock-incubations/{flock_incubation}',
                'pathParams' => [['name' => 'flock_incubation', 'description' => 'Flock incubation batch ID']],
                'queryParams' => [],
            ],
            [
                'name' => 'list_hatch_events',
                'description' => 'List the hatch (birth) events recorded for one incubation batch.',
                'path' => '/flock-incubations/{flock_incubation}/hatch-events',
                'pathParams' => [['name' => 'flock_incubation', 'description' => 'Flock incubation batch ID']],
                'queryParams' => [],
            ],
            [
                'name' => 'list_vendor_stock',
                'description' => 'List product stock allocated to vendedores (per-vendedor inventory).',
                'path' => '/vendor-stock',
                'pathParams' => [],
                'queryParams' => [],
            ],
            [
                'name' => 'get_vendor_stock',
                'description' => 'Get one vendor-stock record (a product allocation to a vendedor) by ID.',
                'path' => '/vendor-stock/{vendor_stock}',
                'pathParams' => [['name' => 'vendor_stock', 'description' => 'Vendor stock record ID']],
                'queryParams' => [],
            ],
            [
                'name' => 'list_sales',
                'description' => 'List all sales, each including its exclusion flag if marked as a one-off event.',
                'path' => '/sales',
                'pathParams' => [],
                'queryParams' => [],
            ],
            [
                'name' => 'get_sale',
                'description' => 'Get one sale by ID, including its exclusion flag if any.',
                'path' => '/sales/{sale}',
                'pathParams' => [['name' => 'sale', 'description' => 'Sale ID']],
                'queryParams' => [],
            ],
            [
                'name' => 'list_stock_transfers',
                'description' => 'List all stock transfers between flock/plantel and vendedores.',
                'path' => '/stock-transfers',
                'pathParams' => [],
                'queryParams' => [],
            ],
            [
                'name' => 'get_stock_transfer',
                'description' => 'Get one stock transfer by ID.',
                'path' => '/stock-transfers/{stock_transfer}',
                'pathParams' => [['name' => 'stock_transfer', 'description' => 'Stock transfer ID']],
                'queryParams' => [],
            ],
            [
                'name' => 'list_daily_productions',
                'description' => 'List daily egg production records by species.',
                'path' => '/daily-productions',
                'pathParams' => [],
                'queryParams' => [],
            ],
            [
                'name' => 'get_daily_production',
                'description' => 'Get one daily production record by ID.',
                'path' => '/daily-productions/{daily_production}',
                'pathParams' => [['name' => 'daily_production', 'description' => 'Daily production record ID']],
                'queryParams' => [],
            ],
            [
                'name' => 'list_expenses',
                'description' => 'List all expenses, each including its species override if one was set.',
                'path' => '/expenses',
                'pathParams' => [],
                'queryParams' => [],
            ],
            [
                'name' => 'get_expense',
                'description' => 'Get one expense by ID, including its species override if any.',
                'path' => '/expenses/{expense}',
                'pathParams' => [['name' => 'expense', 'description' => 'Expense ID']],
                'queryParams' => [],
            ],
            [
                'name' => 'list_cash_flows',
                'description' => 'List cash flow entries (income and outflows).',
                'path' => '/cash-flows',
                'pathParams' => [],
                'queryParams' => [],
            ],
            [
                'name' => 'get_cash_flow',
                'description' => 'Get one cash flow entry by ID.',
                'path' => '/cash-flows/{cash_flow}',
                'pathParams' => [['name' => 'cash_flow', 'description' => 'Cash flow entry ID']],
                'queryParams' => [],
            ],
            [
                'name' => 'list_feed_stocks',
                'description' => 'List feed (ração) stock by type, with current bag/kg balances.',
                'path' => '/feed-stocks',
                'pathParams' => [],
                'queryParams' => [],
            ],
            [
                'name' => 'get_feed_stock',
                'description' => 'Get one feed stock type by ID.',
                'path' => '/feed-stocks/{feed_stock}',
                'pathParams' => [['name' => 'feed_stock', 'description' => 'Feed stock type ID']],
                'queryParams' => [],
            ],
            [
                'name' => 'list_feed_open_logs',
                'description' => 'List the history of opened feed bags (read-only log; written only by the open-bag action).',
                'path' => '/feed-open-logs',
                'pathParams' => [],
                'queryParams' => [],
            ],
            [
                'name' => 'list_flock_cleanings',
                'description' => 'List flock/plantel cleaning records.',
                'path' => '/flock-cleanings',
                'pathParams' => [],
                'queryParams' => [],
            ],
            [
                'name' => 'get_flock_cleaning',
                'description' => 'Get one flock/plantel cleaning record by ID.',
                'path' => '/flock-cleanings/{flock_cleaning}',
                'pathParams' => [['name' => 'flock_cleaning', 'description' => 'Flock cleaning record ID']],
                'queryParams' => [],
            ],
            [
                'name' => 'get_business_line_report',
                'description' => 'Get the Business Line Analysis report comparing quail vs. chicken (revenue, cost, profit, '.
                    'margin), built from sales (excluding ones marked as one-off events), expenses (respecting species '.
                    'overrides), flock and products. Optional start/end date range; omitting both covers the full history.',
                'path' => '/business-line-report',
                'pathParams' => [],
                'queryParams' => [
                    ['name' => 'start', 'description' => 'Start of the period, inclusive, as YYYY-MM-DD. Omit for no lower bound.'],
                    ['name' => 'end', 'description' => 'End of the period, inclusive, as YYYY-MM-DD. Must be >= start. Omit for no upper bound.'],
                ],
            ],
        ];
    }
}
