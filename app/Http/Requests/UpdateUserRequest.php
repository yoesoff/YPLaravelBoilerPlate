<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Leave granular permission logic in controller (canEdit)
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('id'); // assuming route parameter name is 'id'
        return [
            'name' => 'sometimes|string|min:3|max:50',
            'email' => 'sometimes|email|unique:users,email,' . $userId,
            'role' => 'sometimes|in:Administrator,Manager,User',
            'active' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.min' => 'Name must be at least 3 characters.',
            'name.max' => 'Name may not be greater than 50 characters.',
            'email.email' => 'Email format is invalid.',
            'role.in' => 'Role must be one of Administrator, Manager, or User.',
            'active.boolean' => 'Active must be true or false.',
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
