<?php

namespace App\Http\Requests\Mikrotik;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMikrotikRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama' => ['sometimes', 'required', 'string', 'max:100'],
            'ip_address' => ['sometimes', 'required', 'ip'],
            'tipe_koneksi' => ['nullable', 'string', 'max:30'],
            'snmp_status' => ['nullable', 'in:on,off'],
            'script' => ['nullable', 'string'],
            'status' => ['nullable', 'in:online,offline'],
        ];
    }
}