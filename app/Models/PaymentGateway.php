<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentGateway extends Model
{
    protected $table = 'payment_gateways';

    protected $fillable = [
        'gateway', 'credential_source', 'admin_fee',
        'duitku_admin_charge_to', 'duitku_merchant_code', 'duitku_api_key',
        'midtrans_merchant_id', 'midtrans_client_key', 'midtrans_server_key',
        'tripay_merchant_code', 'tripay_api_key', 'tripay_private_key',
        'is_active',
    ];

    protected $casts = [
        'admin_fee' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'duitku_api_key', 'midtrans_server_key', 'tripay_api_key', 'tripay_private_key',
    ];
}