<?php

namespace App\Http\Requests\VendorStock;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'vendedor_id' => [
                'required', 'integer', 'exists:vendedores,id',
                Rule::unique('vendor_stock')->where(fn ($query) => $query->where('product_id', $this->input('product_id'))),
            ],
            'quantity' => ['required', 'integer', 'min:0'],
        ];
    }
}
