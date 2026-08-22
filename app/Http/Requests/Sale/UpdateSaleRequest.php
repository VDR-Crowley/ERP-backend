<?php

namespace App\Http\Requests\Sale;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSaleRequest extends FormRequest
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
        return [
            'date' => ['required', 'date'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'total' => ['required', 'numeric', 'min:0'],
            'payment_pending' => ['required', 'boolean'],
            'buyer' => ['required', 'string', 'max:255'],
            'seller_id' => ['required', 'integer', 'exists:vendedores,id'],
            'delivery_pending' => ['required', 'boolean'],
            'delivery_date' => ['nullable', 'date'],
            'stock_location_type' => ['required', 'in:plantel,vendedor'],
            'stock_location_vendedor_id' => ['nullable', 'integer', 'exists:vendedores,id'],
        ];
    }
}
