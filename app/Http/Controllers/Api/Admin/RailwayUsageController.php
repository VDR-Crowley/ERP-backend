<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\RailwayApiException;
use App\Exceptions\RailwayNotConfiguredException;
use App\Http\Controllers\Controller;
use App\Services\RailwayUsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Consumo/custo do Railway no ciclo de cobrança atual — tela admin pra
 * acompanhar o crédito do plano. Só ADMINISTRADOR (middleware `admin`).
 *
 * `?fresh=1` ignora o cache (default 5 min) — use com moderação, a Railway
 * API tem rate limit.
 */
class RailwayUsageController extends Controller
{
    public function __invoke(Request $request, RailwayUsageService $service): JsonResponse
    {
        try {
            $result = $service->currentUsage($request->boolean('fresh'));
        } catch (RailwayNotConfiguredException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'configured' => false,
                'missing' => $e->missing,
                'how_to_fix' => 'Crie um token de conta/workspace em https://railway.com/account/tokens e configure RAILWAY_API_TOKEN nas variáveis do service App no Railway. RAILWAY_PROJECT_ID já é injetado pelo Railway em produção; localmente, configure à mão.',
            ], 503);
        } catch (RailwayApiException $e) {
            Log::warning('railway-usage: falha na Railway API', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => $e->getMessage(),
                'configured' => true,
            ], 502);
        }

        return response()->json([
            ...$result['data'],
            'configured' => true,
            'cached' => $result['cached'],
        ]);
    }
}
