<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Odp extends Model
{
    protected $table = 'odps';

    protected $fillable = [
        'nama', 'lokasi', 'id_pop', 'latitude', 'longitude',
        'jumlah_port', 'port_terpakai',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    public function pop(): BelongsTo
    {
        return $this->belongsTo(PopArea::class, 'id_pop');
    }

    public function getPortTersisaAttribute(): int
    {
        return max(0, ($this->jumlah_port ?? 0) - ($this->port_terpakai ?? 0));
    }
}