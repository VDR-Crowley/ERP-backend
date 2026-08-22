<?php

namespace App\Http\Requests\DailyProduction;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDailyProductionRequest extends FormRequest
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
            'date' => ['required', 'date', Rule::unique('daily_productions', 'date')->ignore($this->route('daily_production'))],
            'quail_eggs' => ['nullable', 'integer', 'min:0'],
            'chicken_eggs' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
