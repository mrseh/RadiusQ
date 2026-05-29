<?php

namespace App\Models;

class Nas extends Mikrotik
{
    protected function booted(): void
    {
        static::addGlobalScope('nas', function ($builder) {
            // parent scope handled in Mikrotik
        });
    }
}
