<?php

namespace App\Http\Requests\VendorStock;

use Illuminate\Foundation\Http\FormRequest;

class StoreVendorStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Sem `unique` em (product_id, vendedor_id): o controller faz
        // updateOrCreate (upsert), então reenviar o mesmo par atualiza o saldo
        // em vez de ser barrado — necessário pro import ser idempotente.
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'vendedor_id' => ['required', 'integer', 'exists:vendedores,id'],
            'quantity' => ['required', 'integer', 'min:0'],
        ];
    }
}
