<?php

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;

class StoreManualInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_pelanggan' => ['nullable', 'integer'],
            'nama_pelanggan' => ['required', 'string', 'max:100'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'id_profile' => ['nullable', 'integer', 'exists:profiles,id'],
            'id_reseller' => ['nullable', 'integer', 'exists:resellers,id'],
            'tanggal_invoice' => ['nullable', 'date'],
            'tanggal_jatuh_tempo' => ['nullable', 'date'],
            'nominal' => ['required', 'numeric', 'min:0'],
            'keterangan' => ['nullable', 'string', 'max:255'],
            'metode' => ['nullable', 'string', 'max:50'],
        ];
    }
}