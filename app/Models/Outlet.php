<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Outlet extends Model
{
    protected $table = 'outlets';

    protected $fillable = [
        'id_reseller', 'id_biller', 'nama', 'kontak', 'alamat', 'status',
    ];

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class, 'id_reseller');
    }

    public function biller(): BelongsTo
    {
        return $this->belongsTo(Billers::class, 'id_biller');
    }
}