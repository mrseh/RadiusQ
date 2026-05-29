<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyLicense extends Model
{
    protected $table = 'company_licenses';

    protected $fillable = [
        'id_perusahaan', 'id_license_package', 'status',
        'tanggal_mulai', 'tanggal_expired', 'paket_sebelumnya',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_expired' => 'date',
    ];

    public function perusahaan(): BelongsTo
    {
        return $this->belongsTo(Perusahaan::class, 'id_perusahaan');
    }

    public function licensePackage(): BelongsTo
    {
        return $this->belongsTo(LicensePackage::class, 'id_license_package');
    }
}