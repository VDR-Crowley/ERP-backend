<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rotas só de ADMINISTRADOR (alias `admin`). Checa a role de forma estrita
 * (`isAdministrador()`): qualquer outra role — inclusive um VENDEDOR sem
 * `vendedor_id`, que o RestrictVendedor deixa passar — leva 403.
 */
class EnsureAdministrador
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isAdministrador()) {
            abort(403, 'Apenas administradores podem acessar essa área.');
        }

        return $next($request);
    }
}
