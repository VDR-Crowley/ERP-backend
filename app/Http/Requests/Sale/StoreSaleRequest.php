<?php

namespace App\Http\Requests\Sale;

use App\Models\Sale;
use Illuminate\Foundation\Http\FormRequest;

class StoreSaleRequest extends FormRequest
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
            'date' => [
                'required', 'date',
                function ($attribute, $value, $fail): void {
                    $duplicate = Sale::query()
                        ->whereDate('date', $value)
                        ->where('product_id', $this->input('product_id'))
                        ->where('quantity', $this->input('quantity'))
                        ->where('unit_price', $this->input('unit_price'))
                        ->where('buyer', $this->input('buyer'))
                        ->where('seller_id', $this->input('seller_id'))
                        ->exists();

                    if ($duplicate) {
                        $fail('Já existe uma venda com esses dados nesse dia.');
                    }
                },
            ],
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
            'stock_location_vendedor_id' => [
                'required_if:stock_location_type,vendedor',
                'prohibited_if:stock_location_type,plantel',
                'nullable', 'integer', 'exists:vendedores,id',
            ],
        ];
    }
}
