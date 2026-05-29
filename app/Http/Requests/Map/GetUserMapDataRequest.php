<?php

namespace App\Http\Requests\Map;

use Illuminate\Foundation\Http\FormRequest;

class GetUserMapDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'layer' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'max:30'],
            'id_pop' => ['nullable', 'integer'],
            'id_profile' => ['nullable', 'integer'],
        ];
    }
}