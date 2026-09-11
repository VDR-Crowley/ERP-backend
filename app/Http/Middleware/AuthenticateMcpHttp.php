<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autenticação própria da rota HTTP do MCP (`POST /api/mcp`) — não é o
 * Sanctum de usuário (outro propósito: token secreto dedicado, sem usuário
 * associado). 401 se o header `Authorization: Bearer <MCP_HTTP_TOKEN>`
 * estiver ausente, errado, ou se `MCP_HTTP_TOKEN` não estiver configurado —
 * nunca deixa a rota aberta por omissão de config.
 */
class AuthenticateMcpHttp
{
    public function handle(Request $request, Closure $next): Response
    {
        $configured = config('mcp.http_token');
        $provided = $request->bearerToken();

        if (empty($configured) || empty($provided) || ! hash_equals((string) $configured, (string) $provided)) {
            return response()->json([
                'message' => 'Token de acesso ao MCP HTTP ausente ou inválido.',
            ], 401);
        }

        return $next($request);
    }
}
