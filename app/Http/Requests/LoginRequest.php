<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Anyone can attempt to login
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ];
    }

    /**
     * Custom error messages
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Email is required',
            'email.email' => 'Email must be a valid email address',
            'password.required' => 'Password is required',
            'password.min' => 'Password must be at least 6 characters',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation errors',
            'errors' => $validator->errors(),
        ], 422));
    }
}
