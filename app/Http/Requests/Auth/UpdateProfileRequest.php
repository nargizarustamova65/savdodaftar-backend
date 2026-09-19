<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'shop_name' => ['nullable', 'string', 'max:150'],
            'business_type' => ['nullable', 'string', 'max:50'],
            'locale' => ['nullable', 'string', Rule::in(config('savdodaftar.locales', ['uz', 'ru']))],
        ];
    }
}
