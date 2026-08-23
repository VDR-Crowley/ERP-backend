<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EggStock\StoreEggStockRequest;
use App\Http\Requests\EggStock\UpdateEggStockRequest;
use App\Models\EggStock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class EggStockController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(EggStock::all());
    }

    public function store(StoreEggStockRequest $request): JsonResponse
    {
        $eggStock = EggStock::create($request->validated());

        return response()->json($eggStock, Response::HTTP_CREATED);
    }

    public function show(EggStock $eggStock): JsonResponse
    {
        return response()->json($eggStock);
    }

    public function update(UpdateEggStockRequest $request, EggStock $eggStock): JsonResponse
    {
        $eggStock->update($request->validated());

        return response()->json($eggStock);
    }

    public function destroy(EggStock $eggStock): Response
    {
        $eggStock->delete();

        return response()->noContent();
    }
}
