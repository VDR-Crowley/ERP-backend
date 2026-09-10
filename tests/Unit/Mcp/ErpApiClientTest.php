<?php

namespace Tests\Unit\Mcp;

use App\Mcp\ApiUnreachableException;
use App\Mcp\ErpApiClient;
use App\Mcp\ErpApiException;
use App\Mcp\MissingTokenException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ErpApiClientTest extends TestCase
{
    public function test_only_exposes_get_publicly(): void
    {
        $publicMethods = (new \ReflectionClass(ErpApiClient::class))->getMethods(\ReflectionMethod::IS_PUBLIC);
        $names = array_map(fn (\ReflectionMethod $m) => $m->getName(), $publicMethods);

        $this->assertSame(['get'], array_values(array_diff($names, ['__construct'])));
    }

    public function test_internal_request_refuses_non_get_verbs(): void
    {
        Http::fake();

        $client = new ErpApiClient();
        $request = new \ReflectionMethod(ErpApiClient::class, 'request');
        $request->setAccessible(true);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/somente leitura/');

        $request->invoke($client, 'POST', '/sales', [], []);
    }

    public function test_missing_token_fails_before_any_request(): void
    {
        Config::set('mcp.token', null);
        Http::fake();

        try {
            (new ErpApiClient())->get('/sales');
            $this->fail('Esperava MissingTokenException');
        } catch (MissingTokenException $e) {
            Http::assertNothingSent();
        }
    }

    public function test_sends_bearer_token_header(): void
    {
        Config::set('mcp.token', 'abc123');
        Config::set('mcp.base_url', 'https://mcp-test.example/api');
        Http::fake(['*' => Http::response(['id' => 1], 200)]);

        (new ErpApiClient())->get('/sales/1');

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer abc123'));
    }

    public function test_builds_url_with_path_params(): void
    {
        Config::set('mcp.token', 'abc123');
        Config::set('mcp.base_url', 'https://mcp-test.example/api');
        Http::fake(['*' => Http::response([], 200)]);

        (new ErpApiClient())->get('/sales/{sale}', ['sale' => 42]);

        Http::assertSent(fn ($request) => $request->url() === 'https://mcp-test.example/api/sales/42');
    }

    public function test_builds_query_string_omitting_null_values(): void
    {
        Config::set('mcp.token', 'abc123');
        Config::set('mcp.base_url', 'https://mcp-test.example/api');
        Http::fake(['*' => Http::response([], 200)]);

        (new ErpApiClient())->get('/business-line-report', [], ['start' => '2026-01-01', 'end' => null]);

        Http::assertSent(fn ($request) => $request->url() === 'https://mcp-test.example/api/business-line-report?start=2026-01-01');
    }

    public function test_missing_path_param_throws(): void
    {
        Config::set('mcp.token', 'abc123');
        Config::set('mcp.base_url', 'https://mcp-test.example/api');
        Http::fake();

        $this->expectException(\InvalidArgumentException::class);

        (new ErpApiClient())->get('/sales/{sale}', []);
    }

    public function test_exposes_401_body_and_message(): void
    {
        Config::set('mcp.token', 'expired-token');
        Config::set('mcp.base_url', 'https://mcp-test.example/api');
        Http::fake(['*' => Http::response(['message' => 'Unauthenticated.'], 401)]);

        try {
            (new ErpApiClient())->get('/sales');
            $this->fail('Esperava ErpApiException');
        } catch (ErpApiException $e) {
            $this->assertSame(401, $e->status);
            $this->assertStringContainsString('Unauthenticated.', $e->getMessage());
            $this->assertStringContainsString('expiram em 2h', $e->getMessage());
            $this->assertSame(['message' => 'Unauthenticated.'], $e->body);
        }
    }

    public function test_exposes_404_body_and_message(): void
    {
        Config::set('mcp.token', 'abc123');
        Config::set('mcp.base_url', 'https://mcp-test.example/api');
        Http::fake(['*' => Http::response(['message' => 'No query results for model.'], 404)]);

        try {
            (new ErpApiClient())->get('/sales/{sale}', ['sale' => 999]);
            $this->fail('Esperava ErpApiException');
        } catch (ErpApiException $e) {
            $this->assertSame(404, $e->status);
            $this->assertStringContainsString('não existe', $e->getMessage());
            $this->assertStringContainsString('No query results for model.', $e->getMessage());
        }
    }

    public function test_exposes_full_body_for_other_status_codes(): void
    {
        Config::set('mcp.token', 'abc123');
        Config::set('mcp.base_url', 'https://mcp-test.example/api');
        Http::fake(['*' => Http::response('Bad Gateway', 502)]);

        try {
            (new ErpApiClient())->get('/sales');
            $this->fail('Esperava ErpApiException');
        } catch (ErpApiException $e) {
            $this->assertSame(502, $e->status);
            $this->assertStringContainsString('Bad Gateway', $e->getMessage());
        }
    }

    public function test_exposes_network_unreachable(): void
    {
        Config::set('mcp.token', 'abc123');
        Config::set('mcp.base_url', 'https://mcp-test.example/api');
        Http::fake(['*' => fn () => throw new ConnectionException('Connection refused')]);

        $this->expectException(ApiUnreachableException::class);
        $this->expectExceptionMessageMatches('/Connection refused/');

        (new ErpApiClient())->get('/sales');
    }
}
