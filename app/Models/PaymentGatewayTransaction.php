<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentGatewayTransaction extends Model
{
    protected $table = 'payment_gateway_transactions';

    protected $fillable = [
        'gateway_id', 'tanggal', 'ref_id', 'metode', 'kategori',
        'deskripsi', 'gross_amount', 'fee_amount', 'debit', 'kredit', 'saldo', 'gateway',
    ];

    protected $casts = [
        'tanggal' => 'datetime',
        'gross_amount' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'debit' => 'decimal:2',
        'kredit' => 'decimal:2',
        'saldo' => 'decimal:2',
    ];

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class, 'gateway_id');
    }
}