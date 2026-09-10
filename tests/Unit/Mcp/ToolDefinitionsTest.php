<?php

namespace Tests\Unit\Mcp;

use App\Mcp\ToolDefinitions;
use Tests\TestCase;

class ToolDefinitionsTest extends TestCase
{
    private const EXPECTED_NAMES = [
        'get_current_user', 'list_users', 'list_products', 'get_product',
        'list_vendedores', 'get_vendedor', 'list_flock', 'get_flock',
        'list_flock_incubations', 'get_flock_incubation', 'list_hatch_events',
        'list_vendor_stock', 'get_vendor_stock', 'list_sales', 'get_sale',
        'list_stock_transfers', 'get_stock_transfer', 'list_daily_productions',
        'get_daily_production', 'list_expenses', 'get_expense', 'list_cash_flows',
        'get_cash_flow', 'list_feed_stocks', 'get_feed_stock', 'list_feed_open_logs',
        'list_flock_cleanings', 'get_flock_cleaning', 'get_business_line_report',
    ];

    public function test_has_exactly_29_tools(): void
    {
        $this->assertCount(29, ToolDefinitions::all());
    }

    public function test_names_match_expected_list_in_order(): void
    {
        $names = array_map(fn (array $def) => $def['name'], ToolDefinitions::all());

        $this->assertSame(self::EXPECTED_NAMES, $names);
    }

    public function test_names_are_unique(): void
    {
        $names = array_map(fn (array $def) => $def['name'], ToolDefinitions::all());

        $this->assertCount(\count($names), array_unique($names));
    }

    public function test_get_sale_has_required_path_param(): void
    {
        $def = $this->findByName('get_sale');

        $this->assertSame('/sales/{sale}', $def['path']);
        $this->assertSame([['name' => 'sale', 'description' => 'Sale ID']], $def['pathParams']);
        $this->assertSame([], $def['queryParams']);
    }

    public function test_get_business_line_report_has_start_end_query_params(): void
    {
        $def = $this->findByName('get_business_line_report');

        $this->assertSame('/business-line-report', $def['path']);
        $this->assertSame([], $def['pathParams']);
        $this->assertSame(['start', 'end'], array_column($def['queryParams'], 'name'));
    }

    public function test_list_hatch_events_scoped_under_flock_incubation(): void
    {
        $def = $this->findByName('list_hatch_events');

        $this->assertSame('/flock-incubations/{flock_incubation}/hatch-events', $def['path']);
        $this->assertSame([['name' => 'flock_incubation', 'description' => 'Flock incubation batch ID']], $def['pathParams']);
    }

    private function findByName(string $name): array
    {
        foreach (ToolDefinitions::all() as $def) {
            if ($def['name'] === $name) {
                return $def;
            }
        }

        $this->fail("Tool \"{$name}\" não encontrada");
    }
}
