<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sale\StoreSaleExclusionRequest;
use App\Http\Requests\Sale\StoreSaleRequest;
use App\Http\Requests\Sale\UpdateSaleRequest;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SaleController extends Controller
{
    public function __construct(private readonly SaleService $sales) {}

    public function index(Request $request): JsonResponse
    {
        $query = Sale::with('exclusion');

        // Perfil VENDEDOR: só as vendas DELE, e só as do mês atual OU as não
        // pagas (de qualquer mês). Esconde o histórico pago antigo dele.
        $user = $request->user();
        if ($user->isVendedor()) {
            $inicioDoMes = now()->startOfMonth()->toDateString();
            $query->where('seller_id', $user->vendedor_id)
                ->where(function ($q) use ($inicioDoMes) {
                    $q->whereDate('date', '>=', $inicioDoMes)
                        ->orWhere('payment_pending', true);
                });
        }

        return response()->json($query->get());
    }

    /** Cria a venda e baixa o estoque do produto no local informado (`stock_location_type`/`stock_location_vendedor_id`). */
    public function store(StoreSaleRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Perfil VENDEDOR: o backend FORÇA vendedor e local de estoque = o dele
        // (ignora o que a tela mandou), pra ele não lançar venda no nome/estoque
        // de outro. Baixa sempre do estoque do próprio vendedor.
        $user = $request->user();
        if ($user->isVendedor()) {
            $data['seller_id'] = $user->vendedor_id;
            $data['stock_location_type'] = 'vendedor';
            $data['stock_location_vendedor_id'] = $user->vendedor_id;
            $data['stock_location_barn_id'] = null;
        }

        // `skip_stock` (flag do import CLEAN, fora do `validated()`): cria a venda
        // sem baixar estoque — a planilha já traz o saldo final. Ver SaleService.
        $sale = $this->sales->create($data, $request->boolean('skip_stock'));

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
