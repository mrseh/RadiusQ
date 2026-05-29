<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reseller extends Model
{
    protected $table = 'resellers';

    protected $fillable = [
        'nama', 'username', 'whatsapp', 'saldo', 'limit_hutang', 'status',
    ];

    protected $casts = [
        'saldo' => 'decimal:2',
        'limit_hutang' => 'decimal:2',
    ];

    public function billers(): HasMany
    {
        return $this->hasMany(Billers::class, 'id_reseller');
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(Deposit::class, 'id_reseller');
    }

    public function outlets(): HasMany
    {
        return $this->hasMany(Outlet::class, 'id_reseller');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'id_reseller');
    }

    public function transaksis(): HasMany
    {
        return $this->hasMany(Transaksi::class, 'id_reseller');
    }
}