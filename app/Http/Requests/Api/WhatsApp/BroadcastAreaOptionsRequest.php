<?php

namespace App\Http\Requests\Api\WhatsApp;

use Illuminate\Foundation\Http\FormRequest;

class BroadcastAreaOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'area_filter' => ['nullable', 'string'],
        ];
    }
}