<?php

namespace App\Http\Requests\Sales;

use App\Models\Product;
use App\Models\Sale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer'],
            'payment_method' => ['required', Rule::in(Sale::METHODS)],
            // Aralash to'lov uchun
            'paid_cash' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'paid_card' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'debt_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'note' => ['nullable', 'string', 'max:255'],
            'sold_at' => ['nullable', 'date'],
            'client_uuid' => ['nullable', 'uuid'],

            'items' => ['required', 'array', 'min:1', 'max:200'],
            // Ombordagi mahsulot yoki tezkor (nom + narx) mahsulot
            'items.*.product_id' => ['nullable', 'integer', 'required_without:items.*.name'],
            'items.*.name' => ['nullable', 'string', 'max:150', 'required_without:items.*.product_id'],
            'items.*.qty' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'items.*.price' => ['nullable', 'numeric', 'min:0', 'max:999999999999', 'required_without:items.*.product_id'],
            'items.*.buy_price' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'items.*.unit' => ['nullable', Rule::in(Product::UNITS)],
        ];
    }
}
