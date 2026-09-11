<?php

namespace Tests\Feature;

use Tests\TestCase;

class McpHttpTest extends TestCase
{
    /** URL pública real do deploy no Railway — ver docs/MCP.md "Transporte HTTP". */
    private const PRODUCTION_URL = 'https://laravel-production-4c67.up.railway.app/api/mcp';

    private const HEADERS_BASE = [
        'Accept' => 'application/json, text/event-stream',
        'MCP-Protocol-Version' => '2026-07-28',
    ];

    public function test_returns_401_without_authorization_header(): void
    {
        $response = $this->withHeaders(self::HEADERS_BASE + ['Mcp-Method' => 'server/discover'])
            ->postJson('/api/mcp', $this->discoverBody());

        $response->assertStatus(401);
    }

    public function test_returns_401_with_wrong_token(): void
    {
        $response = $this->withHeaders(self::HEADERS_BASE + [
            'Mcp-Method' => 'server/discover',
            'Authorization' => 'Bearer token-errado',
        ])->postJson('/api/mcp', $this->discoverBody());

        $response->assertStatus(401);
    }

    public function test_returns_401_when_mcp_http_token_not_configured(): void
    {
        config(['mcp.http_token' => null]);

        $response = $this->withHeaders(self::HEADERS_BASE + [
            'Mcp-Method' => 'server/discover',
            'Authorization' => 'Bearer '.config('mcp.http_token'), // null vira string vazia
        ])->postJson('/api/mcp', $this->discoverBody());

        $response->assertStatus(401);
    }

    public function test_server_discover_round_trip_with_valid_token(): void
    {
        $response = $this->withHeaders(self::HEADERS_BASE + [
            'Mcp-Method' => 'server/discover',
            'Authorization' => 'Bearer '.config('mcp.http_token'),
        ])->postJson('/api/mcp', $this->discoverBody());

        $response->assertOk();
        $this->assertSame('erp-mcp-php', $response->json('result._meta')['io.modelcontextprotocol/serverInfo']['name']);
        $this->assertSame('complete', $response->json('result.resultType'));
    }

    public function test_tools_call_round_trip_surfaces_real_error_not_generic_failure(): void
    {
        // phpunit.xml configura ERP_API_TOKEN='test-token-123' pros outros testes
        // (ErpApiClientTest etc., que usam Http::fake). Sem isso forçado pra null
        // aqui, a chamada real do list_sales sai pra rede de verdade (DNS falha
        // pra "mcp-test.example") — o que também prova que o erro real chega
        // (cURL error 6 embrulhado em ApiUnreachableException), só que não com
        // o texto "ERP_API_TOKEN". Forçamos `mcp.token` pra null pra exercitar
        // deterministicamente o caminho de token ausente (MissingTokenException),
        // sem depender de rede — mesma técnica usada em
        // McpProtocolRoundTripTest::test_missing_token_surfaces_as_is_error_not_internal_error.
        config(['mcp.token' => null]);

        $response = $this->withHeaders(self::HEADERS_BASE + [
            'Mcp-Method' => 'tools/call',
            'Mcp-Name' => 'list_sales',
            'Authorization' => 'Bearer '.config('mcp.http_token'),
        ])->postJson('/api/mcp', $this->toolCallBody('list_sales', []));

        $response->assertOk();

        // Formato real observado (confirmado rodando o teste, ver task-6-report.md):
        // result.isError === true e result.content[0].text carrega a mensagem
        // real do MissingTokenException — não um -32603/"Error while executing
        // tool" genérico.
        $this->assertTrue($response->json('result.isError'));
        $this->assertStringContainsString('ERP_API_TOKEN', $response->json('result.content.0.text'));
    }

    /**
     * Regressão: os outros testes postam no caminho relativo `/api/mcp`, o que
     * faz o Host virar `localhost` (via APP_URL do ambiente de teste) — e
     * `localhost` está na allowlist padrão do
     * `DnsRebindingProtectionMiddleware` do SDK. Com isso a suíte inteira ficava
     * verde enquanto TODA requisição real no Railway levava
     * `403 Forbidden: Invalid Host header.` (texto puro, nem JSON-RPC).
     *
     * Este teste posta na URL ABSOLUTA de produção justamente pra o header Host
     * ser o de verdade. Se alguém reintroduzir a middleware padrão do SDK sem
     * allowlist adequada, isto quebra.
     */
    public function test_accepts_requests_with_the_real_production_host_header(): void
    {
        $response = $this->withHeaders(self::HEADERS_BASE + [
            'Mcp-Method' => 'server/discover',
            'Authorization' => 'Bearer '.config('mcp.http_token'),
        ])->postJson(self::PRODUCTION_URL, $this->discoverBody());

        $this->assertSame(
            'laravel-production-4c67.up.railway.app',
            $response->baseRequest->getHost(),
            'O teste precisa mesmo sair com o Host de produção, senão não prova nada.'
        );

        $response->assertOk();
        $this->assertSame('erp-mcp-php', $response->json('result._meta')['io.modelcontextprotocol/serverInfo']['name']);
    }

    /**
     * Mesma proteção, pelo outro caminho da middleware do SDK: quando existe
     * header `Origin` (cliente MCP rodando em browser), é o Origin que é
     * checado contra a allowlist, não o Host. CORS de verdade fica com o
     * `HandleCors` do Laravel (config/cors.php), não com o SDK.
     */
    public function test_accepts_requests_carrying_an_origin_header(): void
    {
        $response = $this->withHeaders(self::HEADERS_BASE + [
            'Mcp-Method' => 'server/discover',
            'Authorization' => 'Bearer '.config('mcp.http_token'),
            'Origin' => 'https://claude.ai',
        ])->postJson(self::PRODUCTION_URL, $this->discoverBody());

        $response->assertOk();
    }

    public function test_rate_limit_returns_429_after_the_configured_number_of_requests(): void
    {
        $headers = self::HEADERS_BASE + [
            'Mcp-Method' => 'server/discover',
            'Authorization' => 'Bearer '.config('mcp.http_token'),
        ];

        for ($i = 0; $i < 30; $i++) {
            $this->withHeaders($headers)->postJson('/api/mcp', $this->discoverBody());
        }

        $response = $this->withHeaders($headers)->postJson('/api/mcp', $this->discoverBody());

        $response->assertStatus(429);
    }

    /** @return array<string, mixed> */
    private function discoverBody(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'server/discover',
            'params' => [
                '_meta' => [
                    'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
                    'io.modelcontextprotocol/clientCapabilities' => new \stdClass,
                ],
            ],
        ];
    }

    /** @param array<string, mixed> $arguments
     *  @return array<string, mixed> */
    private function toolCallBody(string $name, array $arguments): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/call',
            'params' => [
                'name' => $name,
                'arguments' => $arguments,
                '_meta' => [
                    'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
                    'io.modelcontextprotocol/clientCapabilities' => new \stdClass,
                ],
            ],
        ];
    }
}
