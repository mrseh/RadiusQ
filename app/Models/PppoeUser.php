<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PppoeUser extends Model
{
    protected $table = 'pppoe_users';

    protected $fillable = [
        'id_pelanggan', 'id_profile', 'id_nas', 'id_pop', 'id_odp', 'id_reseller',
        'username', 'password', 'ip_address', 'session_id', 'session_status',
        'tipe_user', 'nama', 'nik', 'whatsapp', 'alamat', 'koordinat',
        'status', 'jatuh_tempo', 'next_invoice', 'enable_billing',
        'jenis_tagihan', 'siklus_tagihan', 'ppn_percent', 'diskon_rp',
        'harga_paket', 'kode_unik',
    ];

    protected $casts = [
        'jatuh_tempo' => 'date',
        'next_invoice' => 'date',
        'ppn_percent' => 'decimal:2',
        'diskon_rp' => 'decimal:2',
        'harga_paket' => 'decimal:2',
        'enable_billing' => 'boolean',
    ];

    protected $hidden = ['password'];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'id_profile');
    }

    public function nas(): BelongsTo
    {
        return $this->belongsTo(Mikrotik::class, 'id_nas');
    }

    public function pop(): BelongsTo
    {
        return $this->belongsTo(PopArea::class, 'id_pop');
    }

    public function odp(): BelongsTo
    {
        return $this->belongsTo(Odp::class, 'id_odp');
    }

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class, 'id_reseller');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(PppoeSession::class, 'username', 'username');
    }

    public function mapLocation(): HasMany
    {
        return $this->hasMany(UserMapLocation::class, 'id_pppoe_user');
    }
}