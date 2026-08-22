<?php

namespace App\Http\Requests\StockTransfer;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockTransferRequest extends FormRequest
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
            'from_location_type' => ['required', 'in:plantel,vendedor'],
            'from_vendedor_id' => ['nullable', 'integer', 'exists:vendedores,id'],
            'to_location_type' => ['required', 'in:plantel,vendedor'],
            'to_vendedor_id' => ['nullable', 'integer', 'exists:vendedores,id'],
            'note' => ['nullable', 'string'],
        ];
    }
}
