<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class HotspotProfile extends Profile
{
    protected function booted(): void
    {
        static::addGlobalScope('hotspot', function (Builder $builder) {
            $builder->where('tipe', 'hotspot');
        });
    }
}
