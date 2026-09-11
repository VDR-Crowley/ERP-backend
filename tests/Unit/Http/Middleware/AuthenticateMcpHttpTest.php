<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\AuthenticateMcpHttp;
use Illuminate\Http\Request;
use Tests\TestCase;

class AuthenticateMcpHttpTest extends TestCase
{
    public function test_rejects_request_with_no_authorization_header(): void
    {
        config(['mcp.http_token' => 'segredo-correto']);

        $request = Request::create('/api/mcp', 'POST');
        $middleware = new AuthenticateMcpHttp();

        $response = $middleware->handle($request, fn () => $this->fail('next() não deveria rodar'));

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_rejects_request_with_wrong_token(): void
    {
        config(['mcp.http_token' => 'segredo-correto']);

        $request = Request::create('/api/mcp', 'POST');
        $request->headers->set('Authorization', 'Bearer token-errado');
        $middleware = new AuthenticateMcpHttp();

        $response = $middleware->handle($request, fn () => $this->fail('next() não deveria rodar'));

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_rejects_request_when_token_not_configured(): void
    {
        config(['mcp.http_token' => null]);

        $request = Request::create('/api/mcp', 'POST');
        $request->headers->set('Authorization', 'Bearer qualquer-coisa');
        $middleware = new AuthenticateMcpHttp();

        $response = $middleware->handle($request, fn () => $this->fail('next() não deveria rodar'));

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_allows_request_with_correct_token(): void
    {
        config(['mcp.http_token' => 'segredo-correto']);

        $request = Request::create('/api/mcp', 'POST');
        $request->headers->set('Authorization', 'Bearer segredo-correto');
        $middleware = new AuthenticateMcpHttp();

        $called = false;
        $response = $middleware->handle($request, function ($req) use (&$called) {
            $called = true;

            return response('ok');
        });

        $this->assertTrue($called);
        $this->assertSame(200, $response->getStatusCode());
    }
}
