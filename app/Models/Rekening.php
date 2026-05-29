<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rekening extends Model
{
    protected $table = 'rekenings';

    protected $fillable = [
        'id_perusahaan', 'bank', 'norek', 'atas_nama', 'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function perusahaan(): BelongsTo
    {
        return $this->belongsTo(Perusahaan::class, 'id_perusahaan');
    }
}