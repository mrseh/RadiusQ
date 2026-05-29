<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class SavePaymentGatewaySettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['nullable', 'integer', 'exists:payment_gateways,id'],
            'nama' => ['nullable', 'string', 'max:100'],
            'tipe' => ['nullable', 'string', 'max:30'],
            'api_key' => ['nullable', 'string', 'max:255'],
            'server_key' => ['nullable', 'string', 'max:255'],
            'webhook_url' => ['nullable', 'url', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'admin_fee' => ['nullable', 'numeric', 'min:0'],
            'duitku_admin_charge_to' => ['nullable', 'string', 'max:50'],
            'duitku_merchant_code' => ['nullable', 'string', 'max:100'],
            'duitku_api_key' => ['nullable', 'string', 'max:255'],
            'midtrans_merchant_id' => ['nullable', 'string', 'max:100'],
            'midtrans_client_key' => ['nullable', 'string', 'max:255'],
            'midtrans_server_key' => ['nullable', 'string', 'max:255'],
            'tripay_merchant_code' => ['nullable', 'string', 'max:100'],
            'tripay_api_key' => ['nullable', 'string', 'max:255'],
            'tripay_private_key' => ['nullable', 'string', 'max:255'],
        ];
    }
}
