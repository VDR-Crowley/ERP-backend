<?php

namespace App\Http\Controllers\Api;

use App\Mcp\McpServerFactory;
use Illuminate\Http\Request;
use Mcp\Server\Transport\StatelessHttpTransport;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ponto de entrada HTTP do servidor MCP — alternativa ao STDIO de
 * `php artisan mcp:serve`, pro mesmo conjunto de 29 tools. Stateless: cada
 * requisição é uma chamada MCP isolada, via
 * `Mcp\Server\Transport\StatelessHttpTransport` do SDK oficial (não
 * reimplementa o protocolo aqui — só converte Laravel <-> PSR-7).
 *
 * Autenticação (`mcp.http-token`) e rate limit (`throttle:mcp`) ficam na
 * definição da rota (routes/api.php), não aqui.
 */
class McpHttpController
{
    public function __construct(private readonly McpServerFactory $factory) {}

    public function __invoke(Request $request): Response
    {
        $psrRequest = (new PsrHttpFactory)->createRequest($request);

        // `middleware: []` desliga a pilha PADRÃO do SDK de propósito — ela é
        // pensada pra servidor MCP *local* e quebra este deploy por inteiro:
        //
        // - `DnsRebindingProtectionMiddleware` tem allowlist padrão
        //   `['localhost', '127.0.0.1', '[::1]']`. Em produção o Host é
        //   `laravel-production-4c67.up.railway.app` -> 403 "Invalid Host
        //   header." (texto puro, nem JSON-RPC) em TODA requisição real. Pior:
        //   quando existe header `Origin`, é o Origin que ele checa, então
        //   qualquer cliente MCP de browser também levaria 403 mesmo com o Host
        //   na allowlist. O docblock do próprio SDK manda omitir a middleware
        //   quando há reverse proxy validando Host — que é exatamente o caso
        //   aqui (edge do Railway roteia por domínio; requisição com outro Host
        //   nem chega nesta app).
        // - `CorsMiddleware` do SDK, no default (`allowedOrigins: []`), nem
        //   emite `Access-Control-Allow-Origin`. Quem manda em CORS nesta rota
        //   é o `HandleCors` do Laravel, via `config/cors.php` (já cobre
        //   `api/*`).
        //
        // Autenticação e rate limit NÃO dependem disso: são middlewares do
        // Laravel na rota (`mcp.http-token`, `throttle:mcp`), fora da pilha do
        // transporte. Regressão coberta em tests/Feature/McpHttpTest.php, que
        // posta na URL absoluta de produção.
        $transport = new StatelessHttpTransport(
            $this->factory->buildStatelessProtocol(),
            middleware: [],
        );
        $psrResponse = $transport->handle($psrRequest);

        return (new HttpFoundationFactory)->createResponse($psrResponse);
    }
}
