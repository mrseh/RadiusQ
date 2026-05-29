<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Olt extends Model
{
    protected $table = 'olts';

    protected $fillable = [
        'nama', 'ip_address', 'lokasi', 'total_onu', 'status',
    ];
}