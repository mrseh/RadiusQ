<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class WithdrawConfirmRequest extends FormRequest
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
            'otp' => ['required', 'string', 'size:6', 'digits:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'otp.size' => 'Kode OTP harus 6 digit.',
            'otp.digits' => 'Kode OTP harus berupa angka.',
            'jumlah.min' => 'Minimum penarikan adalah Rp 10.000.',
        ];
    }
}
