<?php

namespace Tests\Unit\Mcp;

use App\Mcp\McpServerFactory;
use App\Mcp\ToolDefinitions;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mcp\Capability\Registry\ReferenceHandler;
use Mcp\Exception\ToolCallException;
use Mcp\Server\Session\InMemorySessionStore;
use Mcp\Server\Session\Session;
use Mcp\Server\Stateless\StatelessProtocol;
use Tests\TestCase;

class McpServerFactoryTest extends TestCase
{
    use RefreshDatabase;

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
        $factory = app(McpServerFactory::class);
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
        $factory = app(McpServerFactory::class);
        $factory->build();

        $tool = $factory->registry()->getTool('get_sale')->tool;

        $this->assertSame(['sale'], $tool->inputSchema['required']);
        $this->assertSame('integer', $tool->inputSchema['properties']['sale']['type']);
    }

    public function test_get_business_line_report_tool_has_optional_start_end(): void
    {
        $factory = app(McpServerFactory::class);
        $factory->build();

        $tool = $factory->registry()->getTool('get_business_line_report')->tool;

        $this->assertSame([], $tool->inputSchema['required']);
        $this->assertArrayHasKey('start', $tool->inputSchema['properties']);
        $this->assertArrayHasKey('end', $tool->inputSchema['properties']);
    }

    public function test_tool_annotations_mark_read_only(): void
    {
        $factory = app(McpServerFactory::class);
        $factory->build();

        $annotations = $factory->registry()->getTool('list_sales')->tool->annotations;

        $this->assertTrue($annotations->readOnlyHint);
        $this->assertFalse($annotations->destructiveHint);
        $this->assertTrue($annotations->idempotentHint);
        $this->assertFalse($annotations->openWorldHint);
    }

    public function test_tool_handler_reads_the_real_record_via_eloquent(): void
    {
        $product = Product::factory()->create();

        $factory = app(McpServerFactory::class);
        $factory->build();

        $result = $this->callTool($factory, 'get_product', ['product' => $product->id]);

        $this->assertSame($product->id, $result['id']);
        $this->assertSame($product->name, $result['name']);
    }

    public function test_tool_handler_wraps_not_found_as_tool_call_exception(): void
    {
        $factory = app(McpServerFactory::class);
        $factory->build();

        $this->expectException(ToolCallException::class);

        $this->callTool($factory, 'get_product', ['product' => 999999]);
    }

    public function test_zero_arg_tool_reaches_the_data_reader_through_the_sdk_invocation_path(): void
    {
        Sale::factory()->create();

        $factory = app(McpServerFactory::class);
        $factory->build();

        $result = $this->callTool($factory, 'list_sales', []);

        $this->assertCount(1, $result);
    }

    public function test_build_stateless_protocol_registers_the_same_29_tools(): void
    {
        $factory = app(McpServerFactory::class);
        $protocol = $factory->buildStatelessProtocol();

        $this->assertInstanceOf(StatelessProtocol::class, $protocol);

        $tools = $factory->registry()->getTools();
        $this->assertCount(29, $tools);
    }

    public function test_build_still_returns_a_server_with_29_tools_after_refactor(): void
    {
        // Regression guard for the registerTools() extraction: build() must
        // keep behaving exactly as before.
        $factory = app(McpServerFactory::class);
        $factory->build();

        $this->assertCount(29, $factory->registry()->getTools());
    }
}
