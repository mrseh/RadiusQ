<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HotspotTemplate extends Model
{
    protected $table = 'hotspot_templates';

    protected $fillable = [
        'name', 'content', 'variables', 'status',
    ];

    protected $casts = [
        'variables' => 'array',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
