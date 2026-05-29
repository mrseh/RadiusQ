<?php

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;

class PayInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'paid_by' => ['nullable', 'string', 'max:100'],
            'metode' => ['nullable', 'string', 'max:50'],
        ];
    }
}