<?php

namespace App\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;

class InventoryCountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.actual_stock' => ['required', 'numeric', 'gte:0', 'max:999999999'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
