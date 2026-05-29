<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcsMandiriDevice extends Model
{
    protected $table = 'acs_mandiri_devices';

    protected $fillable = [
        'meta_id', 'ppp_username', 'customer_name', 'id_user', 'mode',
        'status', 'optical_power_dbm', 'uptime', 'sn_ont', 'mac_ont',
        'registration_time', 'last_inform',
    ];

    protected $casts = [
        'registration_time' => 'datetime',
        'last_inform' => 'datetime',
        'optical_power_dbm' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user');
    }
}