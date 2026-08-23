<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Flock\StoreFlockRequest;
use App\Http\Requests\Flock\UpdateFlockRequest;
use App\Models\Flock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class FlockController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Flock::all());
    }

    public function store(StoreFlockRequest $request): JsonResponse
    {
        $flock = Flock::create($request->validated());

        return response()->json($flock, Response::HTTP_CREATED);
    }

    public function show(Flock $flock): JsonResponse
    {
        return response()->json($flock);
    }

    public function update(UpdateFlockRequest $request, Flock $flock): JsonResponse
    {
        $flock->update($request->validated());

        return response()->json($flock);
    }

    public function destroy(Flock $flock): Response
    {
        $flock->delete();

        return response()->noContent();
    }
}
