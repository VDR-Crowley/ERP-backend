<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Barn\StoreBarnRequest;
use App\Http\Requests\Barn\UpdateBarnRequest;
use App\Models\Barn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class BarnController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Barn::all());
    }

    public function store(StoreBarnRequest $request): JsonResponse
    {
        $barn = Barn::create($request->validated());

        return response()->json($barn, Response::HTTP_CREATED);
    }

    public function show(Barn $barn): JsonResponse
    {
        return response()->json($barn);
    }

    public function update(UpdateBarnRequest $request, Barn $barn): JsonResponse
    {
        $barn->update($request->validated());

        return response()->json($barn);
    }

    public function destroy(Barn $barn): Response
    {
        $barn->delete();

        return response()->noContent();
    }
}
