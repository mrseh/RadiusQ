<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class WithdrawRequestOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'jumlah' => ['required', 'numeric', 'min:10000'],
            'id_payment_gateway' => ['required', 'integer', 'exists:payment_gateways,id'],
        ];
    }
}
