<?php

namespace App\Http\Requests\EggLoss;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEggLossRequest extends FormRequest
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
            'species' => ['required', 'in:quail,chicken'],
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
