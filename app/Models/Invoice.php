<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $table = 'invoices';

    protected $fillable = [
        'no_invoice', 'id_pelanggan', 'nama_pelanggan', 'whatsapp',
        'id_profile', 'tanggal_invoice', 'tanggal_jatuh_tempo', 'tanggal_bayar',
        'paid_by', 'nominal', 'id_reseller', 'status', 'keterangan',
        'metode', 'wa_status', 'tagih_status', 'is_overdue',
    ];

    protected $casts = [
        'tanggal_invoice' => 'date',
        'tanggal_jatuh_tempo' => 'date',
        'tanggal_bayar' => 'date',
        'nominal' => 'decimal:2',
        'is_overdue' => 'boolean',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'id_profile');
    }

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class, 'id_reseller');
    }

    public function scopeUnpaid($query)
    {
        return $query->where('status', 'unpaid');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }
}