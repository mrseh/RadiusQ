<?php

namespace App\Http\Requests\Api\Pppoe;

use Illuminate\Foundation\Http\FormRequest;

class StorePppoeProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipe' => ['required', 'in:hotspot,pppoe'],
            'nama' => ['required', 'string', 'max:100'],
            'harga_jual' => ['required', 'numeric', 'min:0'],
            'komisi_reseller' => ['nullable', 'numeric', 'min:0'],
            'rate_limit' => ['nullable', 'string', 'max:50'],
            'group_name' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:aktif,nonaktif'],
        ];
    }
}