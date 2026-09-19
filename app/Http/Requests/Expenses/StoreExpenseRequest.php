<?php

namespace App\Http\Requests\Expenses;

use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(Expense::CATEGORIES)],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'note' => ['nullable', 'string', 'max:255'],
            'spent_at' => ['nullable', 'date_format:Y-m-d'],
            'client_uuid' => ['nullable', 'uuid'],
        ];
    }
}
