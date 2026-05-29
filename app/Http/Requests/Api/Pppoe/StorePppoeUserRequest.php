<?php

namespace App\Http\Requests\Api\Pppoe;

use Illuminate\Foundation\Http\FormRequest;

class StorePppoeUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('id');

        return [
            'username' => [
                'required',
                'string',
                'max:64',
                'unique:pppoe_users,username' . ($userId ? ",{$userId}" : ''),
            ],
            'password' => ['required', 'string', 'min:4', 'max:64'],
            'id_profile' => ['required', 'integer', 'exists:profiles,id'],
            'id_nas' => ['nullable', 'integer', 'exists:mikrotiks,id'],
            'id_pop' => ['nullable', 'integer', 'exists:pop_areas,id'],
            'id_odp' => ['nullable', 'integer', 'exists:odps,id'],
            'id_reseller' => ['nullable', 'integer', 'exists:resellers,id'],
            'tipe_user' => ['required', 'in:pppoe,dhcp'],
            'nama' => ['required', 'string', 'max:150'],
            'nik' => ['nullable', 'string', 'max:20'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'alamat' => ['nullable', 'string', 'max:500'],
            'koordinat' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:aktif,suspend,nonaktif'],
            'jatuh_tempo' => ['nullable', 'date'],
            'enable_billing' => ['nullable', 'boolean'],
            'jenis_tagihan' => ['nullable', 'in:prabayar,pascabayar'],
            'siklus_tagihan' => ['nullable', 'in:fixed_date,renewable'],
            'ppn_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'diskon_rp' => ['nullable', 'numeric', 'min:0'],
            'harga_paket' => ['nullable', 'numeric', 'min:0'],
            'kode_unik' => ['nullable', 'integer'],
        ];
    }
}