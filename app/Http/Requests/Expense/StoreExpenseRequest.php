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
        // Sem checagem de duplicata aqui: a unicidade fica no banco
        // (unique date+description+category+amount). Duplicata vira 409 (ver
        // bootstrap/app.php), que o import ignora em vez de falhar.
        return [
            'date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'quantity' => ['nullable', 'integer', 'min:0'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'amount' => ['required', 'numeric', 'min:0'],
            'paid' => ['required', 'boolean'],
        ];
    }
}
