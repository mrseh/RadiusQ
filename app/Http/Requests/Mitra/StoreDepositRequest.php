<?php

namespace App\Http\Requests\Mitra;

use Illuminate\Foundation\Http\FormRequest;

class StoreDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_reseller' => ['required', 'integer', 'exists:resellers,id'],
            'jumlah' => ['required', 'numeric', 'min:0'],
            'tipe' => ['required', 'in:debit,kredit'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipe.in' => 'Tipe deposit harus debit atau kredit.',
        ];
    }
}
