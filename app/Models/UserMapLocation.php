<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserMapLocation extends Model
{
    protected $table = 'user_map_locations';

    protected $fillable = [
        'id_pppoe_user', 'username', 'full_name', 'id_pelanggan', 'profile',
        'wa', 'nas', 'pop', 'odp', 'latitude', 'longitude', 'address',
        'customer_status', 'internet_status', 'is_online',
        'inet_ip', 'session_id', 'session_status', 'session_ip',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'is_online' => 'boolean',
    ];

    public function pppoeUser(): BelongsTo
    {
        return $this->belongsTo(PppoeUser::class, 'id_pppoe_user');
    }
}