<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppMessage extends Model
{
    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'device_id', 'receiver', 'subject', 'message', 'status', 'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConfig::class, 'device_id');
    }
}