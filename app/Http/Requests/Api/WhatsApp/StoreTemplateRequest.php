<?php

namespace App\Http\Requests\Api\WhatsApp;

use Illuminate\Foundation\Http\FormRequest;

class StoreTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'content' => ['required', 'string', 'max:4096'],
            'variables' => ['nullable', 'array'],
            'variables.*' => ['string', 'max:50'],
        ];
    }
}