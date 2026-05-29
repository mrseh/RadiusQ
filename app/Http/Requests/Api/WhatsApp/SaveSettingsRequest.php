<?php

namespace App\Http\Requests\Api\WhatsApp;

use Illuminate\Foundation\Http\FormRequest;

class SaveSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_id' => ['nullable', 'integer', 'exists:whatsapp_configs,id'],
            'jumlah_pesan_per_batch' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'jeda_antar_batch_menit' => ['nullable', 'integer', 'min:1', 'max:60'],
            'auto_reconnect' => ['nullable', 'boolean'],
            'save_messages' => ['nullable', 'boolean'],
        ];
    }
}