<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Profile extends Model
{
    protected $table = 'profiles';

    protected $fillable = [
        'tipe', 'nama', 'harga_jual', 'komisi_reseller', 'rate_limit',
        'group_name', 'user_count', 'status',
    ];

    protected $casts = [
        'harga_jual' => 'decimal:2',
        'komisi_reseller' => 'decimal:2',
    ];

    public function scopePppoe($query)
    {
        return $query->where('tipe', 'pppoe');
    }

    public function scopeHotspot($query)
    {
        return $query->where('tipe', 'hotspot');
    }

    public function scopeAktif($query)
    {
        return $query->where('status', 'aktif');
    }

    public function pppoeUsers(): HasMany
    {
        return $this->hasMany(PppoeUser::class, 'id_profile');
    }

    public function hotspotUsers(): HasMany
    {
        return $this->hasMany(HotspotUser::class, 'id_profile');
    }
}