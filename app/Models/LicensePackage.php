<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicensePackage extends Model
{
    protected $table = 'license_packages';

    protected $fillable = [
        'nama', 'kategori', 'deskripsi', 'harga_bulanan',
        'nas_limit', 'pppoe_limit', 'hotspot_limit',
        'fitur_payment_gateway', 'fitur_moota', 'fitur_acs_tr069',
        'fitur_olt_management', 'fitur_whatsapp_gateway',
        'fitur_pppoe_online_unlimited', 'fitur_hotspot_online_unlimited',
        'is_active',
    ];

    protected $casts = [
        'harga_bulanan' => 'decimal:2',
        'is_active' => 'boolean',
        'fitur_payment_gateway' => 'boolean',
        'fitur_moota' => 'boolean',
        'fitur_acs_tr069' => 'boolean',
        'fitur_olt_management' => 'boolean',
        'fitur_whatsapp_gateway' => 'boolean',
        'fitur_pppoe_online_unlimited' => 'boolean',
        'fitur_hotspot_online_unlimited' => 'boolean',
    ];
}