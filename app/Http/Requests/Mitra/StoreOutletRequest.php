<?php

namespace App\Http\Requests\Mitra;

use Illuminate\Foundation\Http\FormRequest;

class StoreOutletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_reseller' => ['required', 'integer', 'exists:resellers,id'],
            'id_biller' => ['required', 'integer', 'exists:billers,id'],
            'nama' => ['required', 'string', 'max:100'],
            'kontak' => ['nullable', 'string', 'max:50'],
            'alamat' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:aktif,nonaktif'],
        ];
    }
}
