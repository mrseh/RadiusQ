<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PopArea extends Model
{
    protected $table = 'pop_areas';

    protected $fillable = ['nama', 'lokasi'];
}