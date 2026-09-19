<?php

namespace App\Http\Requests\Products;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:100'],
            'barcode' => ['nullable', 'string', 'max:64'],
            'unit' => ['nullable', Rule::in(Product::UNITS)],
            'buy_price' => ['nullable', 'numeric', 'gte:0', 'max:999999999999'],
            'sell_price' => ['required', 'numeric', 'gte:0', 'max:999999999999'],
            // Boshlang'ich qoldiq (initial harakat sifatida yoziladi)
            'stock' => ['nullable', 'numeric', 'gte:0', 'max:999999999'],
            'min_stock' => ['nullable', 'numeric', 'gte:0', 'max:999999999'],
            'is_active' => ['nullable', 'boolean'],
            'client_uuid' => ['nullable', 'uuid'],
        ];
    }
}
