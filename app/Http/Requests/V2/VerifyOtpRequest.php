<?php

namespace App\Http\Requests\V2;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^\+[1-9]\d{7,14}$/'],
            'otp' => ['required', 'string', 'digits:6'],
            'name' => ['sometimes', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'Phone number is required.',
            'phone.regex' => 'Phone number must be in E.164 format (e.g. +1234567890).',
            'otp.required' => 'OTP code is required.',
            'otp.digits' => 'OTP must be exactly 6 digits.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
        ], 422));
    }
}
