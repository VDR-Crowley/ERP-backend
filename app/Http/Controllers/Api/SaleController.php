<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sale\StoreSaleExclusionRequest;
use App\Http\Requests\Sale\StoreSaleRequest;
use App\Http\Requests\Sale\UpdateSaleRequest;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class SaleController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Sale::with('exclusion')->get());
    }

    /**
     * Sem a baixa de estoque no local (`stockLocation`) ainda — fica pra
     * próxima etapa, junto com o resto da lógica de negócio.
     */
    public function store(StoreSaleRequest $request): JsonResponse
    {
        $sale = Sale::create($request->validated());

        return response()->json($sale, Response::HTTP_CREATED);
    }

    public function show(Sale $sale): JsonResponse
    {
        return response()->json($sale->load('exclusion'));
    }

    public function update(UpdateSaleRequest $request, Sale $sale): JsonResponse
    {
        $sale->update($request->validated());

        return response()->json($sale);
    }

    public function destroy(Sale $sale): Response
    {
        $sale->delete();

        return response()->noContent();
    }

    /** Marca a venda como "evento isolado" (fora da Análise por Linha de Negócio). */
    public function storeExclusion(StoreSaleExclusionRequest $request, Sale $sale): JsonResponse
    {
        $exclusion = $sale->exclusion()->updateOrCreate([], $request->validated());

        return response()->json($exclusion, Response::HTTP_CREATED);
    }

    /** Desmarca a venda (volta a entrar na análise). */
    public function destroyExclusion(Sale $sale): Response
    {
        $sale->exclusion()->delete();

        return response()->noContent();
    }
}
