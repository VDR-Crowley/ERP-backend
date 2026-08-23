<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StockTransfer\StoreStockTransferRequest;
use App\Http\Requests\StockTransfer\UpdateStockTransferRequest;
use App\Models\StockTransfer;
use App\Services\StockTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class StockTransferController extends Controller
{
    public function __construct(private readonly StockTransferService $transfers) {}

    public function index(): JsonResponse
    {
        return response()->json(StockTransfer::all());
    }

    /** Move a quantidade do local de origem pro de destino (exige saldo suficiente na origem). */
    public function store(StoreStockTransferRequest $request): JsonResponse
    {
        $stockTransfer = $this->transfers->create($request->validated());

        return response()->json($stockTransfer, Response::HTTP_CREATED);
    }

    public function show(StockTransfer $stockTransfer): JsonResponse
    {
        return response()->json($stockTransfer);
    }

    public function update(UpdateStockTransferRequest $request, StockTransfer $stockTransfer): JsonResponse
    {
        $stockTransfer = $this->transfers->update($stockTransfer, $request->validated());

        return response()->json($stockTransfer);
    }

    public function destroy(StockTransfer $stockTransfer): Response
    {
        $this->transfers->delete($stockTransfer);

        return response()->noContent();
    }
}
