<?php

namespace App\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;

class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'actual_stock' => ['required', 'numeric', 'gte:0', 'max:999999999'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
