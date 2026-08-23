<?php

namespace App\Http\Requests\EggStock;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEggStockRequest extends FormRequest
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
            'date' => ['required', 'date', Rule::unique('egg_stocks', 'date')->ignore($this->route('egg_stock'))],
            'quail_eggs' => ['nullable', 'integer', 'min:0'],
            'chicken_eggs' => ['nullable', 'integer', 'min:0'],
            'quail_packs' => ['required', 'numeric', 'min:0'],
            'chicken_packs' => ['required', 'numeric', 'min:0'],
            'quail_stock_value' => ['required', 'numeric', 'min:0'],
            'chicken_stock_value' => ['required', 'numeric', 'min:0'],
        ];
    }
}
