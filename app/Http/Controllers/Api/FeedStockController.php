<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FeedStock\OpenFeedStockBagRequest;
use App\Http\Requests\FeedStock\ReplenishFeedStockRequest;
use App\Http\Requests\FeedStock\StoreFeedStockRequest;
use App\Http\Requests\FeedStock\UpdateFeedStockRequest;
use App\Models\FeedStock;
use App\Services\FeedStockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class FeedStockController extends Controller
{
    public function __construct(private readonly FeedStockService $feedStocks) {}

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

    /** Soma sacos/kg repostos e atualiza o peso de referência do saco pro da remessa reposta. */
    public function replenish(ReplenishFeedStockRequest $request, FeedStock $feedStock): JsonResponse
    {
        $feedStock = $this->feedStocks->replenish($feedStock, $request->validated());

        return response()->json($feedStock);
    }

    /** Decrementa 1 saco/peso do saldo e registra o `feed_open_logs` correspondente. */
    public function openBag(OpenFeedStockBagRequest $request, FeedStock $feedStock): JsonResponse
    {
        $feedStock = $this->feedStocks->openBag($feedStock, $request->validated());

        return response()->json($feedStock);
    }
}
