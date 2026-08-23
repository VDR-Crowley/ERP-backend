<?php

namespace App\Http\Requests\FeedStock;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFeedStockRequest extends FormRequest
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
            'type' => ['required', 'string', 'max:255', Rule::unique('feed_stocks', 'type')->ignore($this->route('feed_stock'))],
            'bags_in_stock' => ['required', 'integer', 'min:0'],
            'kg_in_stock' => ['required', 'numeric', 'min:0'],
            'last_bag_weight_kg' => ['required', 'numeric', 'min:0'],
            'expiration_date' => ['nullable', 'date'],
        ];
    }
}
