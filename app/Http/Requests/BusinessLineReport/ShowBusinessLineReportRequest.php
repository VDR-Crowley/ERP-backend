<?php

namespace App\Http\Requests\BusinessLineReport;

use Illuminate\Foundation\Http\FormRequest;

/** Período opcional pro relatório — sem os dois, considera todo o histórico. */
class ShowBusinessLineReportRequest extends FormRequest
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
            'start' => ['nullable', 'date'],
            'end' => ['nullable', 'date', 'after_or_equal:start'],
        ];
    }
}
