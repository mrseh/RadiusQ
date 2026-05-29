<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    protected $table = 'users';

    protected $fillable = [
        'name', 'username', 'email', 'password', 'role', 'whatsapp', 'status',
    ];

    protected $hidden = ['password'];

    public function logs(): HasMany
    {
        return $this->hasMany(Log::class, 'id_user');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isTeknisi(): bool
    {
        return $this->role === 'teknisi';
    }

    public function isReseller(): bool
    {
        return $this->role === 'reseller';
    }
}
