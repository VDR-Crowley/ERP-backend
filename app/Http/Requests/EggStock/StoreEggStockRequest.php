<?php

namespace App\Http\Requests\EggStock;

use Illuminate\Foundation\Http\FormRequest;

class StoreEggStockRequest extends FormRequest
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
            'date' => ['required', 'date', 'unique:egg_stocks,date'],
            // Sem min:0: venda pode ser lançada antes da produção correspondente,
            // gerando saldo de codorna negativo temporário até acertar depois (real no negócio do usuário).
            'quail_eggs' => ['nullable', 'integer'],
            'chicken_eggs' => ['nullable', 'integer', 'min:0'],
            'quail_packs' => ['required', 'numeric'],
            'chicken_packs' => ['required', 'numeric', 'min:0'],
            'quail_stock_value' => ['required', 'numeric'],
            'chicken_stock_value' => ['required', 'numeric', 'min:0'],
        ];
    }
}
