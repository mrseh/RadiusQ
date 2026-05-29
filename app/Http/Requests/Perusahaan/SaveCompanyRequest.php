<?php

namespace App\Http\Requests\Perusahaan;

use Illuminate\Foundation\Http\FormRequest;

class SaveCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:100'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'alamat' => ['nullable', 'string', 'max:255'],
            'singkatan' => ['nullable', 'string', 'max:30'],
            'logo' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:20'],
        ];
    }
}