<?php

namespace Tests\Unit\Mcp;

use App\Mcp\ErpApiClient;
use App\Mcp\McpServerFactory;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Mcp\Server\Transport\StdioTransport;
use Tests\TestCase;

/**
 * Round-trip JSON-RPC de verdade (initialize → tools/call) pelo `StdioTransport`
 * com streams em memória — o mesmo caminho de invocação que `php artisan mcp:serve`
 * usa em produção.
 *
 * Regressão-guard do bug que passou por 174 testes verdes: os testes de unidade
 * chamavam `$reference->handler` como closure crua, o que NUNCA acontece em
 * runtime — o SDK passa pelo `ReferenceHandler`, que mapeia argumentos por nome
 * via reflection. Com `addTool()` toda chamada morria em
 * `RegistryException: Missing required argument \`args\` for Closure` (-32603).
 * Só um teste que fala o protocolo de verdade pega isso.
 */
class McpProtocolRoundTripTest extends TestCase
{
    /**
     * @param  list<array<string, mixed>>  $requests
     * @return list<array<string, mixed>> respostas JSON-RPC decodificadas
     */
    private function roundTrip(array $requests): array
    {
        // Arquivos temporários (e não php://memory) porque StdioTransport::close()
        // fecha os dois streams — o conteúdo precisa sobreviver ao run().
        $inputPath = tempnam(sys_get_temp_dir(), 'mcp-in-');
        $outputPath = tempnam(sys_get_temp_dir(), 'mcp-out-');

        $lines = '';
        foreach ($requests as $request) {
            $lines .= json_encode($request)."\n";
        }
        file_put_contents($inputPath, $lines);

        $input = fopen($inputPath, 'r');
        $output = fopen($outputPath, 'w');

        (new McpServerFactory(new ErpApiClient))->build()->run(new StdioTransport($input, $output));

        $raw = (string) file_get_contents($outputPath);
        @unlink($inputPath);
        @unlink($outputPath);

        $messages = [];
        foreach (explode("\n", trim($raw)) as $line) {
            if (trim($line) !== '') {
                $messages[] = json_decode($line, true);
            }
        }

        return $messages;
    }

    /**
     * @param  list<array<string, mixed>>  $requests
     * @return list<array<string, mixed>>
     */
    private function withHandshake(array $requests): array
    {
        return [
            [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'initialize',
                'params' => [
                    'protocolVersion' => '2025-06-18',
                    'capabilities' => new \stdClass,
                    'clientInfo' => ['name' => 'pest', 'version' => '1.0.0'],
                ],
            ],
            ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'],
            ...$requests,
        ];
    }

    private function findById(array $messages, int $id): array
    {
        foreach ($messages as $message) {
            if (($message['id'] ?? null) === $id) {
                return $message;
            }
        }

        $this->fail('Nenhuma resposta JSON-RPC com id '.$id.' — recebido: '.json_encode($messages));
    }

    public function test_tools_call_with_path_param_returns_the_api_payload(): void
    {
        Config::set('mcp.token', 'abc123');
        Config::set('mcp.base_url', 'https://mcp-test.example/api');
        Http::fake(['*' => Http::response(['id' => 42, 'total' => 10.5], 200)]);

        $messages = $this->roundTrip($this->withHandshake([[
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/call',
            'params' => ['name' => 'get_sale', 'arguments' => ['sale' => 42]],
        ]]));

        $response = $this->findById($messages, 2);

        $this->assertArrayNotHasKey('error', $response, 'tools/call devolveu erro JSON-RPC: '.json_encode($response));
        $this->assertNotTrue($response['result']['isError'] ?? false, 'tools/call devolveu isError: '.json_encode($response));
        $this->assertSame(['id' => 42, 'total' => 10.5], json_decode($response['result']['content'][0]['text'], true));
        Http::assertSent(fn ($request) => $request->url() === 'https://mcp-test.example/api/sales/42');
    }

    public function test_tools_call_without_arguments_reaches_the_api_client(): void
    {
        Config::set('mcp.token', 'abc123');
        Config::set('mcp.base_url', 'https://mcp-test.example/api');
        Http::fake(['*' => Http::response([['id' => 1]], 200)]);

        $messages = $this->roundTrip($this->withHandshake([[
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => ['name' => 'list_sales', 'arguments' => new \stdClass],
        ]]));

        $response = $this->findById($messages, 3);

        $this->assertArrayNotHasKey('error', $response, 'tools/call devolveu erro JSON-RPC: '.json_encode($response));
        $this->assertNotTrue($response['result']['isError'] ?? false, 'tools/call devolveu isError: '.json_encode($response));
        Http::assertSent(fn ($request) => $request->url() === 'https://mcp-test.example/api/sales');
    }

    public function test_api_error_surfaces_as_is_error_with_the_real_message(): void
    {
        Config::set('mcp.token', 'abc123');
        Config::set('mcp.base_url', 'https://mcp-test.example/api');
        Http::fake(['*' => Http::response(['message' => 'No query results for model.'], 404)]);

        $messages = $this->roundTrip($this->withHandshake([[
            'jsonrpc' => '2.0',
            'id' => 4,
            'method' => 'tools/call',
            'params' => ['name' => 'get_sale', 'arguments' => ['sale' => 999]],
        ]]));

        $response = $this->findById($messages, 4);

        $this->assertArrayNotHasKey('error', $response, 'esperava isError, veio erro JSON-RPC: '.json_encode($response));
        $this->assertTrue($response['result']['isError']);
        $this->assertStringContainsString('No query results for model.', $response['result']['content'][0]['text']);
    }

    public function test_missing_token_surfaces_as_is_error_not_internal_error(): void
    {
        Config::set('mcp.token', null);
        Config::set('mcp.base_url', 'https://mcp-test.example/api');

        $messages = $this->roundTrip($this->withHandshake([[
            'jsonrpc' => '2.0',
            'id' => 5,
            'method' => 'tools/call',
            'params' => ['name' => 'list_sales', 'arguments' => new \stdClass],
        ]]));

        $response = $this->findById($messages, 5);

        $this->assertArrayNotHasKey('error', $response, 'esperava isError, veio erro JSON-RPC: '.json_encode($response));
        $this->assertTrue($response['result']['isError']);
        $this->assertStringContainsString('ERP_API_TOKEN', $response['result']['content'][0]['text']);
    }

    public function test_tools_list_advertises_the_29_tools(): void
    {
        $messages = $this->roundTrip($this->withHandshake([
            ['jsonrpc' => '2.0', 'id' => 6, 'method' => 'tools/list', 'params' => new \stdClass],
        ]));

        $response = $this->findById($messages, 6);

        $this->assertArrayNotHasKey('error', $response, json_encode($response));
        $this->assertCount(29, $response['result']['tools']);
    }
}
