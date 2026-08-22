<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendedor\StoreVendedorRequest;
use App\Http\Requests\Vendedor\UpdateVendedorRequest;
use App\Models\Vendedor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class VendedorController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Vendedor::all());
    }

    public function store(StoreVendedorRequest $request): JsonResponse
    {
        $vendedor = Vendedor::create($request->validated());

        return response()->json($vendedor, Response::HTTP_CREATED);
    }

    public function show(Vendedor $vendedor): JsonResponse
    {
        return response()->json($vendedor);
    }

    public function update(UpdateVendedorRequest $request, Vendedor $vendedor): JsonResponse
    {
        $vendedor->update($request->validated());

        return response()->json($vendedor);
    }

    public function destroy(Vendedor $vendedor): Response
    {
        $vendedor->delete();

        return response()->noContent();
    }
}
