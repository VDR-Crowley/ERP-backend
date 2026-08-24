<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EggLoss\StoreEggLossRequest;
use App\Http\Requests\EggLoss\UpdateEggLossRequest;
use App\Models\EggLoss;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class EggLossController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(EggLoss::all());
    }

    public function store(StoreEggLossRequest $request): JsonResponse
    {
        $eggLoss = EggLoss::create($request->validated());

        return response()->json($eggLoss, Response::HTTP_CREATED);
    }

    public function show(EggLoss $eggLoss): JsonResponse
    {
        return response()->json($eggLoss);
    }

    public function update(UpdateEggLossRequest $request, EggLoss $eggLoss): JsonResponse
    {
        $eggLoss->update($request->validated());

        return response()->json($eggLoss);
    }

    public function destroy(EggLoss $eggLoss): Response
    {
        $eggLoss->delete();

        return response()->noContent();
    }
}
