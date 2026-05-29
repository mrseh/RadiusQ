<?php

namespace App\Http\Requests\Api\Tiket;

use Illuminate\Foundation\Http\FormRequest;

class StoreTiketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_pelanggan' => ['nullable', 'string', 'max:150'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'jenis_gangguan' => ['nullable', 'string', 'max:100'],
            'prioritas' => ['nullable', 'in:low,medium,high,critical'],
            'deskripsi' => ['nullable', 'string', 'max:2000'],
        ];
    }
}