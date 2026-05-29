<?php

namespace App\Http\Requests\Api\Olt;

use Illuminate\Foundation\Http\FormRequest;

class StoreOltRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $oltId = $this->route('olt');

        return [
            'nama' => ['required', 'string', 'max:150'],
            'ip_address' => ['required', 'ip'],
            'snmp_community' => ['nullable', 'string', 'max:100'],
            'snmp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'type' => ['nullable', 'in:huawei,huawei_gpon,generic'],
            'lokasi' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:online,offline,unknown'],
        ];
    }
}