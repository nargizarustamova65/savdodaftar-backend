<?php

namespace App\Http\Requests\Sales;

use App\Models\SaleReturn;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReturnSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Bo'sh bo'lsa — to'liq qaytarish
            'items' => ['nullable', 'array', 'max:200'],
            'items.*.sale_item_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'refund_method' => ['nullable', Rule::in(SaleReturn::REFUND_METHODS)],
            'reason' => ['nullable', 'string', 'max:255'],
            'returned_at' => ['nullable', 'date'],
            'client_uuid' => ['nullable', 'uuid'],
        ];
    }
}
