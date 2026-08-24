<?php

namespace App\Http\Requests\Expense;

use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
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
                    $duplicate = Expense::query()
                        ->whereDate('date', $value)
                        ->where('description', $this->input('description'))
                        ->where('category', $this->input('category'))
                        ->where('amount', $this->input('amount'))
                        ->exists();

                    if ($duplicate) {
                        $fail('Já existe uma despesa com esses dados nesse dia.');
                    }
                },
            ],
            'description' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'quantity' => ['nullable', 'integer', 'min:0'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'amount' => ['required', 'numeric', 'min:0'],
            'paid' => ['required', 'boolean'],
        ];
    }
}
