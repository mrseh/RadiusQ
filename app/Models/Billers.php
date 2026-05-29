<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Billers extends Model
{
    protected $table = 'billers';

    protected $fillable = [
        'id_reseller', 'nama', 'username', 'whatsapp', 'alamat', 'status',
    ];

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class, 'id_reseller');
    }
}