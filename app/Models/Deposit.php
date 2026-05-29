<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Deposit extends Model
{
    protected $table = 'deposits';

    protected $fillable = [
        'id_reseller', 'jumlah', 'tipe', 'keterangan',
    ];

    protected $casts = [
        'tanggal' => 'datetime',
        'paid_at' => 'datetime',
        'nominal' => 'decimal:2',
    ];

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class, 'id_reseller');
    }
}