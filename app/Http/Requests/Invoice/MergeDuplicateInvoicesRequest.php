<?php

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;

class MergeDuplicateInvoicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_id' => ['required', 'integer', 'exists:invoices,id'],
            'source_ids' => ['required', 'array', 'min:1'],
            'source_ids.*' => ['required', 'integer', 'exists:invoices,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'target_id.required' => 'Invoice target harus dipilih.',
            'source_ids.required' => 'Invoice sumber harus dipilih.',
        ];
    }
}