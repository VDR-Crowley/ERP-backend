<?php

namespace App\Http\Requests\FeedStock;

use Illuminate\Foundation\Http\FormRequest;

class StoreFeedStockRequest extends FormRequest
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
            'type' => ['required', 'string', 'max:255', 'unique:feed_stocks,type'],
            'bags_in_stock' => ['required', 'integer', 'min:0'],
            'kg_in_stock' => ['required', 'numeric', 'min:0'],
            'last_bag_weight_kg' => ['required', 'numeric', 'min:0'],
            'expiration_date' => ['nullable', 'date'],
        ];
    }
}
