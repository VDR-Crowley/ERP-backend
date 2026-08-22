<?php

namespace App\Http\Requests\DailyProduction;

use Illuminate\Foundation\Http\FormRequest;

class StoreDailyProductionRequest extends FormRequest
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
            'date' => ['required', 'date', 'unique:daily_productions,date'],
            'quail_eggs' => ['nullable', 'integer', 'min:0'],
            'chicken_eggs' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
