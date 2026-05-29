<?php

namespace App\Http\Requests\Mitra;

use Illuminate\Foundation\Http\FormRequest;

class StoreResellerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:50', 'unique:resellers,username'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'saldo' => ['nullable', 'numeric', 'min:0'],
            'limit_hutang' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'in:aktif,nonaktif'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.unique' => 'Username sudah digunakan.',
        ];
    }
}
