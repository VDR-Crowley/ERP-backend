<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BarnStock\StoreBarnStockRequest;
use App\Models\BarnStock;
use Illuminate\Http\JsonResponse;

class BarnStockController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(BarnStock::all());
    }

    /** Define (upsert) o saldo de um produto num galpão — usado pra editar estoque direto na tela. */
    public function store(StoreBarnStockRequest $request): JsonResponse
    {
        $data = $request->validated();
        $barnStock = BarnStock::updateOrCreate(
            ['barn_id' => $data['barn_id'], 'product_id' => $data['product_id']],
            ['quantity' => $data['quantity']],
        );

        return response()->json($barnStock);
    }
}
