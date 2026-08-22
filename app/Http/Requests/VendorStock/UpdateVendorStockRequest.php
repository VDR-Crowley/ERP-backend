<?php

namespace App\Http\Requests\VendorStock;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVendorStockRequest extends FormRequest
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
                Rule::unique('vendor_stock')
                    ->where(fn ($query) => $query->where('product_id', $this->input('product_id')))
                    ->ignore($this->route('vendor_stock')),
            ],
            'quantity' => ['required', 'integer', 'min:0'],
        ];
    }
}
