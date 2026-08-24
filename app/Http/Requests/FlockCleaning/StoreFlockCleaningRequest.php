<?php

namespace App\Http\Requests\FlockCleaning;

use App\Models\FlockCleaning;
use Illuminate\Foundation\Http\FormRequest;

class StoreFlockCleaningRequest extends FormRequest
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
                    $duplicate = FlockCleaning::query()
                        ->whereDate('date', $value)
                        ->where('species', $this->input('species'))
                        ->where('cleaning_type', $this->input('cleaning_type'))
                        ->exists();

                    if ($duplicate) {
                        $fail('Já existe uma higienização com esses dados nesse dia.');
                    }
                },
            ],
            'species' => ['required', 'in:quail,chicken'],
            'cleaning_type' => ['required', 'in:total,feeder,tray,nest'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
