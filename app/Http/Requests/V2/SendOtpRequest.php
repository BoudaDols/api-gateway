<?php

namespace App\Http\Requests\V2;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SendOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'phone' => ['required', 'string', 'regex:/^\+[1-9]\d{7,14}$/'],
        ];

        // name is only required for registration
        if ($this->routeIs('v2.auth.register')) {
            $rules['name'] = ['required', 'string', 'max:255'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'Phone number is required.',
            'phone.regex' => 'Phone number must be in E.164 format (e.g. +1234567890).',
            'name.required' => 'Name is required for registration.',
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
