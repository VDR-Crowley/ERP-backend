<?php

namespace Tests\Unit\Mcp;

use App\Mcp\ErpApiClient;
use App\Mcp\McpServerFactory;
use App\Mcp\ToolDefinitions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Mcp\Capability\Registry\ReferenceHandler;
use Mcp\Exception\ToolCallException;
use Mcp\Server\Session\InMemorySessionStore;
use Mcp\Server\Session\Session;
use Mcp\Server\Stateless\StatelessProtocol;
use Tests\TestCase;

class McpServerFactoryTest extends TestCase
{
    /**
     * Invoca uma tool registrada do jeito que o SDK invoca em runtime: pelo
     * `ReferenceHandler`, com o bag de argumentos + `_session`, como o
     * `CallToolHandler` faz. NUNCA chamar `$reference->handler` direto como
     * closure — foi exatamente isso que deixou 174 testes verdes com o servidor
     * quebrado (o `ReferenceHandler` mapeia argumentos por reflection e a
     * closure de `addTool()` explodia com -32603). Round-trip JSON-RPC completo
     * fica em `McpProtocolRoundTripTest`.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function callTool(McpServerFactory $factory, string $name, array $arguments): mixed
    {
        $reference = $factory->registry()->getTool($name);
        $arguments['_session'] = new Session(new InMemorySessionStore);

        return (new ReferenceHandler)->handle($reference, $arguments);
    }

    public function test_registers_exactly_29_tools_with_expected_names(): void
    {
        $factory = new McpServerFactory(new ErpApiClient);
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
        $factory = new McpServerFactory(new ErpApiClient);
        $factory->build();

        $tool = $factory->registry()->getTool('get_sale')->tool;

        $this->assertSame(['sale'], $tool->inputSchema['required']);
        $this->assertSame('integer', $tool->inputSchema['properties']['sale']['type']);
    }

    public function test_get_business_line_report_tool_has_optional_start_end(): void
    {
        $factory = new McpServerFactory(new ErpApiClient);
        $factory->build();

        $tool = $factory->registry()->getTool('get_business_line_report')->tool;

        $this->assertSame([], $tool->inputSchema['required']);
        $this->assertArrayHasKey('start', $tool->inputSchema['properties']);
        $this->assertArrayHasKey('end', $tool->inputSchema['properties']);
    }

    public function test_tool_annotations_mark_read_only(): void
    {
        $factory = new McpServerFactory(new ErpApiClient);
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

        $factory = new McpServerFactory(new ErpApiClient);
        $factory->build();

        $result = $this->callTool($factory, 'get_sale', ['sale' => 42]);

        $this->assertSame(['id' => 42, 'total' => 10.5], $result);
        Http::assertSent(fn ($request) => $request->url() === 'https://mcp-test.example/api/sales/42');
    }

    public function test_tool_handler_wraps_api_errors_as_tool_call_exception(): void
    {
        Config::set('mcp.token', 'abc123');
        Config::set('mcp.base_url', 'https://mcp-test.example/api');
        Http::fake(['*' => Http::response(['message' => 'No query results for model.'], 404)]);

        $factory = new McpServerFactory(new ErpApiClient);
        $factory->build();

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessageMatches('/No query results for model\./');

        $this->callTool($factory, 'get_sale', ['sale' => 999]);
    }

    public function test_zero_arg_tool_reaches_the_api_client_through_the_sdk_invocation_path(): void
    {
        Config::set('mcp.token', 'abc123');
        Config::set('mcp.base_url', 'https://mcp-test.example/api');
        Http::fake(['*' => Http::response([['id' => 1]], 200)]);

        $factory = new McpServerFactory(new ErpApiClient);
        $factory->build();

        $result = $this->callTool($factory, 'list_sales', []);

        $this->assertSame([['id' => 1]], $result);
        Http::assertSent(fn ($request) => $request->url() === 'https://mcp-test.example/api/sales');
    }

    public function test_build_stateless_protocol_registers_the_same_29_tools(): void
    {
        $factory = new McpServerFactory(new ErpApiClient);
        $protocol = $factory->buildStatelessProtocol();

        $this->assertInstanceOf(StatelessProtocol::class, $protocol);

        $tools = $factory->registry()->getTools();
        $this->assertCount(29, $tools);
    }

    public function test_build_still_returns_a_server_with_29_tools_after_refactor(): void
    {
        // Regression guard for the registerTools() extraction: build() must
        // keep behaving exactly as before.
        $factory = new McpServerFactory(new ErpApiClient);
        $factory->build();

        $this->assertCount(29, $factory->registry()->getTools());
    }
}
