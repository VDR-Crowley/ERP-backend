<?php

namespace App\Http\Requests\FeedStock;

use App\Models\FeedOpenLog;
use Illuminate\Foundation\Http\FormRequest;

class OpenFeedStockBagRequest extends FormRequest
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
                    $feedStock = $this->route('feed_stock');

                    $duplicate = FeedOpenLog::query()
                        ->whereDate('date', $value)
                        ->where('feed_type', $feedStock?->type)
                        ->where('weight_kg', $this->input('weight_kg'))
                        ->exists();

                    if ($duplicate) {
                        $fail('Já existe um saco aberto com esses dados nesse dia.');
                    }
                },
            ],
            'weight_kg' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
