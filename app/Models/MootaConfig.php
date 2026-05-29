<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MootaConfig extends Model
{
    protected $table = 'moota_configs';

    protected $fillable = [
        'username', 'api_key', 'webhook_url', 'webhook_secret',
        'is_active', 'last_sync',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_sync' => 'datetime',
    ];
}