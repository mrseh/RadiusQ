<?php

namespace App\Http\Requests\Hotspot;

use Illuminate\Foundation\Http\FormRequest;

class StoreHotspotProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'rate_limit' => ['nullable', 'string', 'max:50'],
            'valid_for' => ['nullable', 'integer', 'min:1'],
            'shared_users' => ['nullable', 'integer', 'min:1', 'max:10'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ];
    }
}
