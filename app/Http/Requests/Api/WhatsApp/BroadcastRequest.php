<?php

namespace App\Http\Requests\Api\WhatsApp;

use Illuminate\Foundation\Http\FormRequest;

class BroadcastRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_id' => ['required', 'integer', 'exists:whatsapp_configs,id'],
            'recipients' => ['required', 'array', 'min:1'],
            'recipients.*' => ['required', 'string', 'max:20'],
            'message' => ['required', 'string', 'max:4096'],
            'type' => ['nullable', 'in:text,image,document'],
        ];
    }
}