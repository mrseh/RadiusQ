<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mikrotik extends Model
{
    protected $table = 'mikrotiks';

    protected $fillable = [
        'nama', 'ip_address', 'tipe_koneksi', 'snmp_status',
        'script', 'status',
    ];
}