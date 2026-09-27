<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FeedOpenLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Criação é via POST /feed-stocks/{feedStock}/open-bag (FeedStockController),
 * não diretamente por aqui. O destroy existe só pro import CLEAN zerar os logs
 * antes de recarregar (o log não tem update; sem apagar, reimportar duplicaria).
 */
class FeedOpenLogController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(FeedOpenLog::all());
    }

    /** Remove um log de saco aberto (usado pelo import CLEAN pra zerar antes de recarregar). */
    public function destroy(FeedOpenLog $feedOpenLog): Response
    {
        $feedOpenLog->delete();

        return response()->noContent();
    }
}
