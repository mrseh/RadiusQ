<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppConfig extends Model
{
    protected $table = 'whatsapp_configs';

    protected $fillable = [
        'provider', 'jumlah_pesan_per_batch', 'jeda_antar_batch_menit',
        'device_number', 'device_name', 'device_url',
        'device_status', 'is_default', 'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function messages(): BelongsTo
    {
        return $this->hasMany(WhatsAppMessage::class, 'device_id');
    }
}