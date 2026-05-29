<?php

namespace App\Http\Requests\Hotspot;

use Illuminate\Foundation\Http\FormRequest;

class StoreHotspotUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_profile' => ['required', 'integer', 'exists:profiles,id'],
            'id_nas' => ['nullable', 'integer', 'exists:mikrotiks,id'],
            'id_pop' => ['nullable', 'integer', 'exists:pop_areas,id'],
            'id_odp' => ['nullable', 'integer', 'exists:odps,id'],
            'id_reseller' => ['nullable', 'integer', 'exists:resellers,id'],
            'username' => ['required', 'string', 'max:50', 'unique:hotspot_users,username'],
            'password' => ['required', 'string', 'min:4', 'max:100'],
            'nama' => ['required', 'string', 'max:100'],
            'nik' => ['nullable', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'alamat' => ['nullable', 'string', 'max:255'],
            'koordinat' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'in:aktif,suspend,nonaktif'],
            'jatuh_tempo' => ['nullable', 'date'],
            'enable_billing' => ['nullable', 'boolean'],
            'ppn_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'harga_paket' => ['nullable', 'numeric', 'min:0'],
            'kode_unik' => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }
}
