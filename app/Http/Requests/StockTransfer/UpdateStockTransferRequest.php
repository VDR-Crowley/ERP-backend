<?php

namespace App\Http\Requests\StockTransfer;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStockTransferRequest extends FormRequest
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
            'from_location_type' => ['required', 'in:plantel,vendedor,barn'],
            'from_vendedor_id' => [
                'required_if:from_location_type,vendedor',
                'nullable', 'integer', 'exists:vendedores,id',
            ],
            'from_location_barn_id' => [
                'required_if:from_location_type,barn',
                'nullable', 'integer', 'exists:barn,id',
            ],
            'to_location_type' => ['required', 'in:plantel,vendedor,barn'],
            'to_vendedor_id' => [
                'required_if:to_location_type,vendedor',
                'nullable', 'integer', 'exists:vendedores,id',
            ],
            'to_location_barn_id' => [
                'required_if:to_location_type,barn',
                'nullable', 'integer', 'exists:barn,id',
            ],
            'note' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $fromType = $this->input('from_location_type');
            $toType = $this->input('to_location_type');
            if ($fromType === null || $toType === null) {
                return;
            }

            $sameLocation = $fromType === $toType
                && ($fromType === 'plantel'
                    || ($fromType === 'vendedor' && $this->input('from_vendedor_id') == $this->input('to_vendedor_id'))
                    || ($fromType === 'barn' && $this->input('from_location_barn_id') == $this->input('to_location_barn_id')));

            if ($sameLocation) {
                $validator->errors()->add('to_location_type', 'O destino da transferência não pode ser igual à origem.');
            }
        });
    }
}
