<?php

namespace App\Http\Requests\Hotspot;

use Illuminate\Foundation\Http\FormRequest;

class BulkReactivateHotspotUserRequest extends FormRequest
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
            'billing_cycle' => ['nullable', 'string', 'in:monthly,weekly'],
            'start_date' => ['nullable', 'date'],
        ];
    }
}
