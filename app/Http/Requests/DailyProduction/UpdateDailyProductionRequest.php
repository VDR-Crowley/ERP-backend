<?php

namespace App\Http\Requests\DailyProduction;

use App\Models\DailyProduction;
use Illuminate\Foundation\Http\FormRequest;

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
            'barn_id' => ['nullable', 'integer', 'exists:barn,id'],
            // Ver nota no Store: comparação por `whereDate`, ignorando o próprio id.
            'date' => ['required', 'date', $this->uniquePerBarnRule()],
            'quail_eggs' => ['nullable', 'integer', 'min:0'],
            'chicken_eggs' => ['nullable', 'integer', 'min:0'],
        ];
    }

    private function uniquePerBarnRule(): \Closure
    {
        $barnId = $this->input('barn_id');
        $ignoreId = $this->route('daily_production')?->id;

        return function (string $attribute, mixed $value, \Closure $fail) use ($barnId, $ignoreId): void {
            $exists = DailyProduction::whereDate('date', $value)
                ->when($barnId === null, fn ($q) => $q->whereNull('barn_id'))
                ->when($barnId !== null, fn ($q) => $q->where('barn_id', $barnId))
                ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists();

            if ($exists) {
                $fail('Já existe um registro de produção nesse dia para esse galpão.');
            }
        };
    }
}
