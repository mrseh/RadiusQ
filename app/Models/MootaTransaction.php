<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MootaTransaction extends Model
{
    protected $table = 'moota_transactions';

    protected $fillable = [
        'gateway_id', 'tanggal', 'description', 'amount', 'type', 'akun',
    ];

    protected $casts = [
        'tanggal' => 'datetime',
        'amount' => 'decimal:2',
    ];

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class, 'gateway_id');
    }
}