<?php

namespace App\Http\Requests\Products;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'barcode' => ['sometimes', 'nullable', 'string', 'max:64'],
            'unit' => ['sometimes', Rule::in(Product::UNITS)],
            'buy_price' => ['sometimes', 'nullable', 'numeric', 'gte:0', 'max:999999999999'],
            'sell_price' => ['sometimes', 'required', 'numeric', 'gte:0', 'max:999999999999'],
            'min_stock' => ['sometimes', 'numeric', 'gte:0', 'max:999999999'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
