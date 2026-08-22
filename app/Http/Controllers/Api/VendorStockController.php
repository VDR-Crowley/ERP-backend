<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\VendorStock\StoreVendorStockRequest;
use App\Http\Requests\VendorStock\UpdateVendorStockRequest;
use App\Models\VendorStock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class VendorStockController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(VendorStock::all());
    }

    public function store(StoreVendorStockRequest $request): JsonResponse
    {
        $vendorStock = VendorStock::create($request->validated());

        return response()->json($vendorStock, Response::HTTP_CREATED);
    }

    public function show(VendorStock $vendorStock): JsonResponse
    {
        return response()->json($vendorStock);
    }

    public function update(UpdateVendorStockRequest $request, VendorStock $vendorStock): JsonResponse
    {
        $vendorStock->update($request->validated());

        return response()->json($vendorStock);
    }

    public function destroy(VendorStock $vendorStock): Response
    {
        $vendorStock->delete();

        return response()->noContent();
    }
}
