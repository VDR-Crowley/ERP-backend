<?php

namespace App\Http\Requests\BarnStock;

use Illuminate\Foundation\Http\FormRequest;

class StoreBarnStockRequest extends FormRequest
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
            'barn_id' => ['required', 'integer', 'exists:barn,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer'],
        ];
    }
}
