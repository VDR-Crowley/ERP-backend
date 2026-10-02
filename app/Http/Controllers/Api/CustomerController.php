<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Perfil VENDEDOR: só os clientes que compraram DELE (buyers das vendas
        // dele). O histórico/telefone desses clientes é tudo que ele enxerga.
        $user = $request->user();
        if ($user->isVendedor()) {
            $compradores = Sale::where('seller_id', $user->vendedor_id)
                ->whereNotNull('buyer')
                ->where('buyer', '<>', '')
                ->distinct()
                ->pluck('buyer');

            return response()->json(Customer::whereIn('name', $compradores)->get());
        }

        return response()->json(Customer::all());
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = Customer::create($request->validated());

        return response()->json($customer, Response::HTTP_CREATED);
    }

    public function show(Customer $customer): JsonResponse
    {
        return response()->json($customer);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $customer->update($request->validated());

        return response()->json($customer);
    }

    public function destroy(Customer $customer): Response
    {
        $customer->delete();

        return response()->noContent();
    }
}
