<?php

namespace App\Http\Requests\FlockCleaning;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFlockCleaningRequest extends FormRequest
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
            'cleaning_type' => ['required', 'in:total,feeder,tray,nest'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
