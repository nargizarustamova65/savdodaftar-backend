<?php

namespace App\Http\Requests\Auth;

use App\Http\Concerns\NormalizesPhone;
use App\Models\OtpCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendOtpRequest extends FormRequest
{
    use NormalizesPhone;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['phone' => $this->normalizePhone($this->input('phone'))]);
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^\+998\d{9}$/'],
            'purpose' => ['nullable', 'string', Rule::in(OtpCode::PURPOSES)],
        ];
    }

    public function purpose(): string
    {
        return $this->input('purpose') ?: OtpCode::PURPOSE_LOGIN;
    }
}
