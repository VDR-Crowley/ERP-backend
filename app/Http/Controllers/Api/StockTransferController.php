<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StockTransfer\StoreStockTransferRequest;
use App\Http\Requests\StockTransfer\UpdateStockTransferRequest;
use App\Models\StockTransfer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class StockTransferController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(StockTransfer::all());
    }

    public function store(StoreStockTransferRequest $request): JsonResponse
    {
        $stockTransfer = StockTransfer::create($request->validated());

        return response()->json($stockTransfer, Response::HTTP_CREATED);
    }

    public function show(StockTransfer $stockTransfer): JsonResponse
    {
        return response()->json($stockTransfer);
    }

    public function update(UpdateStockTransferRequest $request, StockTransfer $stockTransfer): JsonResponse
    {
        $stockTransfer->update($request->validated());

        return response()->json($stockTransfer);
    }

    public function destroy(StockTransfer $stockTransfer): Response
    {
        $stockTransfer->delete();

        return response()->noContent();
    }
}
