<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentGatewayWithdraw extends Model
{
    protected $table = 'payment_gateway_withdraws';

    protected $fillable = [
        'gateway_id', 'tanggal', 'withdraw_id', 'nama_bank',
        'no_rekening', 'atas_nama', 'amount', 'fee', 'status',
    ];

    protected $casts = [
        'tanggal' => 'datetime',
        'amount' => 'decimal:2',
        'fee' => 'decimal:2',
    ];

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class, 'gateway_id');
    }
}