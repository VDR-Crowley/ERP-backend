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

        $transport = new StatelessHttpTransport($this->factory->buildStatelessProtocol());
        $psrResponse = $transport->handle($psrRequest);

        return (new HttpFoundationFactory)->createResponse($psrResponse);
    }
}
