<?php

namespace App\Http\Requests\Hotspot;

use Illuminate\Foundation\Http\FormRequest;

class RekapVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'id_reseller' => ['nullable', 'integer', 'exists:resellers,id'],
            'status' => ['nullable', 'string', 'in:aktif,suspend,nonaktif'],
        ];
    }
}
