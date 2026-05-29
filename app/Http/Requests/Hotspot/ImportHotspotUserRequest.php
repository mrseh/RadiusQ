<?php

namespace App\Http\Requests\Hotspot;

use Illuminate\Foundation\Http\FormRequest;

class ImportHotspotUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:5120'],
            'id_profile' => ['required', 'integer', 'exists:profiles,id'],
            'id_nas' => ['nullable', 'integer', 'exists:mikrotiks,id'],
            'id_reseller' => ['nullable', 'integer', 'exists:resellers,id'],
        ];
    }
}
