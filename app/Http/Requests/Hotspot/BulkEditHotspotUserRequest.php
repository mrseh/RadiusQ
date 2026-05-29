<?php

namespace App\Http\Requests\Hotspot;

use Illuminate\Foundation\Http\FormRequest;

class BulkEditHotspotUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:hotspot_users,id'],
            'id_profile' => ['nullable', 'integer', 'exists:profiles,id'],
            'id_nas' => ['nullable', 'integer', 'exists:mikrotiks,id'],
            'status' => ['nullable', 'string', 'in:aktif,suspend,nonaktif'],
            'jatuh_tempo' => ['nullable', 'date'],
            'enable_billing' => ['nullable', 'boolean'],
            'ppn_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'harga_paket' => ['nullable', 'numeric', 'min:0'],
            'kode_unik' => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }
}
