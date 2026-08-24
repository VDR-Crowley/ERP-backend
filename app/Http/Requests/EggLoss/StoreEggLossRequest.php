<?php

namespace App\Http\Requests\EggLoss;

use App\Models\EggLoss;
use Illuminate\Foundation\Http\FormRequest;

class StoreEggLossRequest extends FormRequest
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
                    $reason = $this->input('reason');

                    $duplicate = EggLoss::query()
                        ->whereDate('date', $value)
                        ->where('species', $this->input('species'))
                        ->where('quantity', $this->input('quantity'))
                        ->when(
                            $reason === null,
                            fn ($query) => $query->whereNull('reason'),
                            fn ($query) => $query->where('reason', $reason),
                        )
                        ->exists();

                    if ($duplicate) {
                        $fail('Já existe uma perda de ovos com esses dados nesse dia.');
                    }
                },
            ],
            'species' => ['required', 'in:quail,chicken'],
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
