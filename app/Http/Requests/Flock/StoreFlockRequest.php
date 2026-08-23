<?php

namespace App\Http\Requests\Flock;

use Illuminate\Foundation\Http\FormRequest;

class StoreFlockRequest extends FormRequest
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
            'species' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:0'],
            'feed_bags_per_month' => ['required', 'integer', 'min:0'],
            'bag_price' => ['required', 'numeric', 'min:0'],
            'monthly_total' => ['required', 'numeric', 'min:0'],
        ];
    }
}
