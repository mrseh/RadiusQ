<?php

namespace App\Http\Requests\Log;

use Illuminate\Foundation\Http\FormRequest;

class ClearLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'current_password'],
        ];
    }

    public function messages(): array
    {
        return [
            'password.current_password' => 'Password yang Anda masukkan salah.',
        ];
    }
}