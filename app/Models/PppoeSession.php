<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PppoeSession extends Model
{
    protected $table = 'pppoe_sessions';

    protected $fillable = [
        'id_pppoe_user', 'username', 'session_id', 'nas', 'nas_ip',
        'ip_address', 'mac_address', 'uptime', 'input_bytes', 'output_bytes',
        'start_time', 'update_time', 'stop_time', 'status', 'is_unknown',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'update_time' => 'datetime',
        'stop_time' => 'datetime',
    ];

    public function pppoeUser(): BelongsTo
    {
        return $this->belongsTo(PppoeUser::class, 'id_pppoe_user');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }
}