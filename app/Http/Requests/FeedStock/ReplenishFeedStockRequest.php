<?php

namespace App\Http\Requests\FeedStock;

use Illuminate\Foundation\Http\FormRequest;

class ReplenishFeedStockRequest extends FormRequest
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
            'bags' => ['required', 'integer', 'min:1'],
            'bag_weight_kg' => ['required', 'numeric', 'min:0.01'],
            'expiration_date' => ['required', 'date'],
        ];
    }
}
