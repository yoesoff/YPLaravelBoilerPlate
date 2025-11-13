<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Leave role gate in controller for now; return true here.
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'name' => 'required|string|min:3|max:50',
            // Future: 'role' => 'sometimes|in:Administrator,Manager,User',
            // Future: 'active' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Email is required.',
            'email.email' => 'Email format is invalid.',
            'email.unique' => 'Email already exists.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters.',
            'name.required' => 'Name is required.',
            'name.min' => 'Name must be at least 3 characters.',
            'name.max' => 'Name may not be greater than 50 characters.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'error' => 'validation_failed',
                'messages' => $validator->errors(),
            ], 422)
        );
    }
}
