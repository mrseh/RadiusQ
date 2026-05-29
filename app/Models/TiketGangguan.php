<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TiketGangguan extends Model
{
    protected $table = 'tiket_gangguans';

    protected $fillable = [
        'nomor_tiket', 'id_user', 'user_type', 'nama_pelanggan',
        'jenis_gangguan', 'prioritas', 'status', 'teknisi_id',
        'nama_teknisi', 'deskripsi', 'solusi', 'tanggal_selesai',
    ];

    protected $casts = [
        'tanggal_selesai' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function teknisi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teknisi_id');
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['open', 'proses', 'pending']);
    }
}