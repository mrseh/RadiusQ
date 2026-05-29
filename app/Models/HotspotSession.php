<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotspotSession extends Model
{
    protected $table = 'hotspot_sessions';

    protected $fillable = [
        'username', 'nas', 'ip_address', 'mac_address',
        'input_octets', 'output_octets', 'session_time',
        'start_time', 'terminate_cause',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'input_octets' => 'integer',
        'output_octets' => 'integer',
        'session_time' => 'integer',
    ];

    protected $hidden = ['terminate_cause'];

    public function hotspotUser(): BelongsTo
    {
        return $this->belongsTo(HotspotUser::class, 'username', 'username');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('terminate_cause')
            ->where('start_time', '>', now()->subMinutes(30));
    }
}
