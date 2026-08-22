<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DailyProduction\StoreDailyProductionRequest;
use App\Http\Requests\DailyProduction\UpdateDailyProductionRequest;
use App\Models\DailyProduction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class DailyProductionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(DailyProduction::all());
    }

    public function store(StoreDailyProductionRequest $request): JsonResponse
    {
        $dailyProduction = DailyProduction::create($request->validated());

        return response()->json($dailyProduction, Response::HTTP_CREATED);
    }

    public function show(DailyProduction $dailyProduction): JsonResponse
    {
        return response()->json($dailyProduction);
    }

    public function update(UpdateDailyProductionRequest $request, DailyProduction $dailyProduction): JsonResponse
    {
        $dailyProduction->update($request->validated());

        return response()->json($dailyProduction);
    }

    public function destroy(DailyProduction $dailyProduction): Response
    {
        $dailyProduction->delete();

        return response()->noContent();
    }
}
