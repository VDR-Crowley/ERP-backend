<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FeedOpenLog;
use Illuminate\Http\JsonResponse;

/**
 * Somente leitura: o log é criado via POST /feed-stocks/{feedStock}/open-bag
 * (FeedStockController), não diretamente por aqui.
 */
class FeedOpenLogController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(FeedOpenLog::all());
    }
}
