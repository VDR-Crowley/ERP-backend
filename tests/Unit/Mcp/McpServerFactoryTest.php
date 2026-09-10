<?php

namespace Tests\Unit\Mcp;

use App\Mcp\ErpApiClient;
use App\Mcp\McpServerFactory;
use App\Mcp\ToolDefinitions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Mcp\Exception\ToolCallException;
use Tests\TestCase;

class McpServerFactoryTest extends TestCase
{
    public function test_registers_exactly_29_tools_with_expected_names(): void
    {
        $factory = new McpServerFactory(new ErpApiClient());
        $factory->build();

        $tools = $factory->registry()->getTools();

        $this->assertCount(29, $tools);
        $this->assertSame(
            array_map(fn (array $def) => $def['name'], ToolDefinitions::all()),
            array_keys((array) $tools->references),
        );
    }

    public function test_get_sale_tool_requires_integer_sale_id(): void
    {
        $factory = new McpServerFactory(new ErpApiClient());
        $factory->build();

        $tool = $factory->registry()->getTool('get_sale')->tool;

        $this->assertSame(['sale'], $tool->inputSchema['required']);
        $this->assertSame('integer', $tool->inputSchema['properties']['sale']['type']);
    }

    public function test_get_business_line_report_tool_has_optional_start_end(): void
    {
        $factory = new McpServerFactory(new ErpApiClient());
        $factory->build();

        $tool = $factory->registry()->getTool('get_business_line_report')->tool;

        $this->assertSame([], $tool->inputSchema['required']);
        $this->assertArrayHasKey('start', $tool->inputSchema['properties']);
        $this->assertArrayHasKey('end', $tool->inputSchema['properties']);
    }

    public function test_tool_annotations_mark_read_only(): void
    {
        $factory = new McpServerFactory(new ErpApiClient());
        $factory->build();

        $annotations = $factory->registry()->getTool('list_sales')->tool->annotations;

        $this->assertTrue($annotations->readOnlyHint);
        $this->assertFalse($annotations->destructiveHint);
        $this->assertTrue($annotations->idempotentHint);
        $this->assertFalse($annotations->openWorldHint);
    }

    public function test_tool_handler_calls_the_api_client_and_returns_its_result(): void
    {
        Config::set('mcp.token', 'abc123');
        Config::set('mcp.base_url', 'https://mcp-test.example/api');
        Http::fake(['*' => Http::response(['id' => 42, 'total' => 10.5], 200)]);

        $factory = new McpServerFactory(new ErpApiClient());
        $factory->build();

        $handler = $factory->registry()->getTool('get_sale')->handler;
        $result = $handler(['sale' => 42]);

        $this->assertSame(['id' => 42, 'total' => 10.5], $result);
        Http::assertSent(fn ($request) => $request->url() === 'https://mcp-test.example/api/sales/42');
    }

    public function test_tool_handler_wraps_api_errors_as_tool_call_exception(): void
    {
        Config::set('mcp.token', 'abc123');
        Config::set('mcp.base_url', 'https://mcp-test.example/api');
        Http::fake(['*' => Http::response(['message' => 'No query results for model.'], 404)]);

        $factory = new McpServerFactory(new ErpApiClient());
        $factory->build();

        $handler = $factory->registry()->getTool('get_sale')->handler;

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessageMatches('/No query results for model\./');

        $handler(['sale' => 999]);
    }
}
