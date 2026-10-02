<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforcement do perfil VENDEDOR: um usuário com role VENDEDOR só acessa um
 * punhado de rotas (ver vendas dele, criar venda, ver CRM dele, ler produtos +
 * o essencial de auth). Qualquer outra rota -> 403. O ESCOPO dos dados (só as
 * vendas/clientes dele) é aplicado dentro de SaleController/CustomerController;
 * aqui a gente só barra as áreas que ele nem pode tocar. Admin passa livre.
 */
class RestrictVendedor
{
    /** Rotas de negócio liberadas pro VENDEDOR (por nome de rota). */
    private const ALLOWED_NAMES = [
        'sales.index',      // ver as vendas dele (escopadas no controller)
        'sales.store',      // criar venda (vendedor/local forçados no controller)
        'customers.index',  // ver o CRM dele (escopado no controller)
        'products.index',   // ler produtos pra montar a venda (preço/nome) + estoque do Plantel disponível
        'vendedores.index', // ler vendedores — o front resolve o nome do vendedor
                            // da venda por aqui; sem isso a lista de vendas nem carrega
        'vendor-stock.index', // ver o estoque DELE (escopado no controller) — saldo +/-
    ];

    /** Rotas essenciais de auth liberadas (não têm nome). */
    private const ALLOWED_URIS = [
        'api/user',
        'api/logout',
        'api/refresh',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isVendedor()) {
            $name = $request->route()?->getName();
            $uri = $request->route()?->uri();

            if (! in_array($name, self::ALLOWED_NAMES, true) && ! in_array($uri, self::ALLOWED_URIS, true)) {
                abort(403, 'Seu perfil de vendedor não tem acesso a essa área.');
            }
        }

        return $next($request);
    }
}
