<?php

namespace App\Http\Requests\Api\Transaksi;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransaksiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_reseller' => ['nullable', 'integer', 'exists:resellers,id'],
            'jenis' => ['required', 'in:pemasukan,pengeluaran'],
            'kategori' => ['nullable', 'string', 'max:100'],
            'metode' => ['nullable', 'string', 'max:50'],
            'deskripsi' => ['nullable', 'string', 'max:500'],
            'qty' => ['nullable', 'numeric', 'min:0'],
            'nominal' => ['required', 'numeric', 'min:0'],
            'tanggal' => ['nullable', 'date'],
        ];
    }
}