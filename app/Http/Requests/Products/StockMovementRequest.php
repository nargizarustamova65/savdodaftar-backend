<?php

namespace App\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Kirim va chiqim uchun umumiy so'rov.
 */
class StockMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'qty' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            // Kirimda yangi tannarx (ixtiyoriy)
            'buy_price' => ['nullable', 'numeric', 'gte:0', 'max:999999999999'],
            'update_buy_price' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:255'],
            'created_at' => ['nullable', 'date'],
            'client_uuid' => ['nullable', 'uuid'],
        ];
    }
}
