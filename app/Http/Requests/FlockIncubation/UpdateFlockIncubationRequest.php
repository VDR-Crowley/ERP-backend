<?php

namespace App\Http\Requests\FlockIncubation;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFlockIncubationRequest extends FormRequest
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
            'start_date' => ['required', 'date'],
            'species' => ['required', 'in:quail,chicken'],
            'egg_count' => ['required', 'integer', 'min:1'],
            'expected_hatch_date' => ['required', 'date'],
            'status' => ['required', 'in:incubando,eclodido'],
            'egg_cost' => ['nullable', 'numeric', 'min:0'],
            'feed_cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
