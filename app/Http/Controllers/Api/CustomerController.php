<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Vendedor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CustomerController extends Controller
{
    /**
     * Lista os clientes JÁ com os agregados do CRM (última compra, nº compras,
     * total, vendedor da última) calculados no backend a partir de TODAS as
     * vendas da pessoa. Importante pro VENDEDOR: o `/sales` dele é escopado
     * (mês/não pagas), então agregar no front daria "nunca" pros clientes com
     * venda antiga/paga — aqui a conta usa o histórico completo dele.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isVendedor = $user->isVendedor();

        // Vendas que contam pro CRM: admin = todas; vendedor = todas as DELE
        // (sem o filtro mês/não-paga — o CRM precisa do histórico inteiro).
        $vendas = Sale::query()
            ->whereNotNull('buyer')
            ->where('buyer', '<>', '')
            ->when($isVendedor, fn ($q) => $q->where('seller_id', $user->vendedor_id))
            ->get(['buyer', 'date', 'total', 'seller_id']);

        $porComprador = $vendas->groupBy('buyer');
        $nomeVendedor = Vendedor::pluck('name', 'id');

        // Clientes: vendedor só os que compraram dele; admin todos.
        $clientes = $isVendedor
            ? Customer::whereIn('name', $porComprador->keys())->get()
            : Customer::all();

        $data = $clientes->map(function (Customer $c) use ($porComprador, $nomeVendedor) {
            $compras = ($porComprador->get($c->name) ?? collect())->sortByDesc('date')->values();
            $ultima = $compras->first();

            return [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
                'last_purchase' => $ultima ? substr((string) $ultima->date, 0, 10) : null,
                'purchase_count' => $compras->count(),
                'total' => round($compras->sum(fn ($s) => (float) $s->total), 2),
                'last_seller' => $ultima && $ultima->seller_id ? ($nomeVendedor[$ultima->seller_id] ?? null) : null,
            ];
        });

        return response()->json($data->values());
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
