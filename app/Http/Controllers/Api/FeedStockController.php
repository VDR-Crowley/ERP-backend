<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FeedStock\StoreFeedStockRequest;
use App\Http\Requests\FeedStock\UpdateFeedStockRequest;
use App\Models\FeedStock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class FeedStockController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(FeedStock::all());
    }

    public function store(StoreFeedStockRequest $request): JsonResponse
    {
        $feedStock = FeedStock::create($request->validated());

        return response()->json($feedStock, Response::HTTP_CREATED);
    }

    public function show(FeedStock $feedStock): JsonResponse
    {
        return response()->json($feedStock);
    }

    public function update(UpdateFeedStockRequest $request, FeedStock $feedStock): JsonResponse
    {
        $feedStock->update($request->validated());

        return response()->json($feedStock);
    }

    public function destroy(FeedStock $feedStock): Response
    {
        $feedStock->delete();

        return response()->noContent();
    }

    /**
     * Esqueleto: reposição de sacos (recalcular bags_in_stock/kg_in_stock,
     * trocar validade) fica pra próxima etapa (lógica de negócio).
     */
    public function replenish(FeedStock $feedStock): JsonResponse
    {
        return response()->json(['message' => 'Reposição de ração ainda não implementada.'], Response::HTTP_NOT_IMPLEMENTED);
    }

    /**
     * Esqueleto: abrir saco deveria criar um `feed_open_logs` e abater do
     * saldo do tipo — fica pra próxima etapa (lógica de negócio).
     */
    public function openBag(FeedStock $feedStock): JsonResponse
    {
        return response()->json(['message' => 'Abertura de saco ainda não implementada.'], Response::HTTP_NOT_IMPLEMENTED);
    }
}
