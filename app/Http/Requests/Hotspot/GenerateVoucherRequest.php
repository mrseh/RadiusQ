<?php

namespace App\Http\Requests\Hotspot;

use Illuminate\Foundation\Http\FormRequest;

class GenerateVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_profile' => ['required', 'integer', 'exists:profiles,id'],
            'id_reseller' => ['nullable', 'integer', 'exists:resellers,id'],
            'jumlah' => ['required', 'integer', 'min:1', 'max:1000'],
            'prefix' => ['nullable', 'string', 'max:10'],
            'password_length' => ['nullable', 'integer', 'min:4', 'max:12'],
            'tanggal_aktif' => ['nullable', 'date'],
            'masa_berlaku' => ['nullable', 'date'],
            'enable_billing' => ['nullable', 'boolean'],
        ];
    }
}
