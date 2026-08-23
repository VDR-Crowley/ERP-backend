<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sale\StoreSaleExclusionRequest;
use App\Http\Requests\Sale\StoreSaleRequest;
use App\Http\Requests\Sale\UpdateSaleRequest;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class SaleController extends Controller
{
    public function __construct(private readonly SaleService $sales) {}

    public function index(): JsonResponse
    {
        return response()->json(Sale::with('exclusion')->get());
    }

    /** Cria a venda e baixa o estoque do produto no local informado (`stock_location_type`/`stock_location_vendedor_id`). */
    public function store(StoreSaleRequest $request): JsonResponse
    {
        $sale = $this->sales->create($request->validated());

        return response()->json($sale, Response::HTTP_CREATED);
    }

    public function show(Sale $sale): JsonResponse
    {
        return response()->json($sale->load('exclusion'));
    }

    /** Desfaz a baixa antiga e aplica a nova (mesmo se produto/local/quantidade mudaram). */
    public function update(UpdateSaleRequest $request, Sale $sale): JsonResponse
    {
        $sale = $this->sales->update($sale, $request->validated());

        return response()->json($sale);
    }

    /** Devolve a quantidade baixada por essa venda pro local de onde saiu. */
    public function destroy(Sale $sale): Response
    {
        $this->sales->delete($sale);

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
