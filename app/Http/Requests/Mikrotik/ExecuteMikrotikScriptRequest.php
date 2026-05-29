<?php

namespace App\Http\Requests\Mikrotik;

use Illuminate\Foundation\Http\FormRequest;

class ExecuteMikrotikScriptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'script' => ['required', 'string'],
        ];
    }
}