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
        // Sem checagem de duplicata aqui: a unicidade fica no banco
        // (unique date+species+cleaning_type). Duplicata vira 409 (ver
        // bootstrap/app.php), que o import ignora em vez de falhar.
        return [
            'date' => ['required', 'date'],
            'species' => ['required', 'in:quail,chicken'],
            'cleaning_type' => ['required', 'in:total,feeder,tray,nest'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
