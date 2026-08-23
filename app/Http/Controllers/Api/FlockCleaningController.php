<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FlockCleaning\StoreFlockCleaningRequest;
use App\Http\Requests\FlockCleaning\UpdateFlockCleaningRequest;
use App\Models\FlockCleaning;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class FlockCleaningController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(FlockCleaning::all());
    }

    public function store(StoreFlockCleaningRequest $request): JsonResponse
    {
        $flockCleaning = FlockCleaning::create($request->validated());

        return response()->json($flockCleaning, Response::HTTP_CREATED);
    }

    public function show(FlockCleaning $flockCleaning): JsonResponse
    {
        return response()->json($flockCleaning);
    }

    public function update(UpdateFlockCleaningRequest $request, FlockCleaning $flockCleaning): JsonResponse
    {
        $flockCleaning->update($request->validated());

        return response()->json($flockCleaning);
    }

    public function destroy(FlockCleaning $flockCleaning): Response
    {
        $flockCleaning->delete();

        return response()->noContent();
    }
}
