<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CashFlow\StoreCashFlowRequest;
use App\Http\Requests\CashFlow\UpdateCashFlowRequest;
use App\Models\CashFlow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class CashFlowController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(CashFlow::all());
    }

    public function store(StoreCashFlowRequest $request): JsonResponse
    {
        $cashFlow = CashFlow::create($request->validated());

        return response()->json($cashFlow, Response::HTTP_CREATED);
    }

    public function show(CashFlow $cashFlow): JsonResponse
    {
        return response()->json($cashFlow);
    }

    public function update(UpdateCashFlowRequest $request, CashFlow $cashFlow): JsonResponse
    {
        $cashFlow->update($request->validated());

        return response()->json($cashFlow);
    }

    public function destroy(CashFlow $cashFlow): Response
    {
        $cashFlow->delete();

        return response()->noContent();
    }
}
