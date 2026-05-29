<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * RadNas Model
 *
 * FreeRADIUS radnas table — NAS (Network Access Server) clients
 *
 * Menyimpan data NAS/Mikrotik yang terdaftar sebagai RADIUS client.
 * FreeRADIUS menggunakan tabel ini untuk:
 * - Validasi client requests (secret validation)
 * - Audit trail per NAS
 * - Load balance (jika multiple NAS)
 *
 * Di Laravel FRRADIUS, tabel ini di-sync dari tabel 'nas' Laravel
 * saat NAS dibuat/diubah melalui NasObserver.
 *
 * NOTE: Named 'radnas' untuk avoid conflict dengan tabel 'nas'
 * yang sudah ada di ROADMAP_PART1 (tabel master Mikrotik di Laravel).
 */
class RadNas extends Model
{
    /**
     * Tabel di database (FreeRADIUS table)
     */
    protected $table = 'radnas';

    /**
     * Primary key
     */
    protected $primaryKey = 'id';

    /**
     * Tidak pakai timestamps Laravel
     */
    public $timestamps = false;

    /**
     * Field yang bisa di-fill via mass assignment
     */
    protected $fillable = [
        'nasname',
        'shortname',
        'type',
        'ports',
        'secret',
        'server',
        'community',
        'description',
    ];

    /**
     * Cast tipe data
     */
    protected $casts = [
        'nasname'    => 'string',
        'shortname'  => 'string',
        'type'       => 'string',
        'ports'      => 'integer',
        'secret'     => 'string',
        'server'     => 'string',
        'community'  => 'string',
        'description' => 'string',
    ];

    // =============================================================
    // CONSTANTS — NAS Types
    // =============================================================

    const TYPE_MIKROTIK = 'mikrotik';
    const TYPE_CISCO    = 'cisco';
    const TYPE_OTHER    = 'other';

    const TYPE_LABELS = [
        self::TYPE_MIKROTIK => 'Mikrotik',
        self::TYPE_CISCO    => 'Cisco',
        self::TYPE_OTHER    => 'Other',
    ];

    // =============================================================
    // SYNC METHODS
    // =============================================================

    /**
     * Sync NAS dari tabel Laravel 'mikrotiks' ke FreeRADIUS radnas
     *
     * Dipanggil saat:
     * - Mikrotik dibuat (Observer::created)
     * - Mikrotik diubah (Observer::updated)
     * - Batch sync via RadiusSyncService::syncNasDevices()
     *
     * @param Mikrotik $mikrotik Mikrotik model dari Laravel
     * @return void
     */
    public static function syncFromMikrotik(Mikrotik $mikrotik): void
    {
        $secret = $mikrotik->script ?? 'testing123';
        $type = self::TYPE_MIKROTIK;

        self::updateOrCreate(
            ['nasname' => $mikrotik->ip_address],
            [
                'shortname'   => $mikrotik->nama,
                'type'        => $type,
                'ports'       => 1812,
                'secret'      => $secret,
                'server'      => '',
                'community'   => 'public',
                'description' => "Synced from Laravel Mikrotik #{$mikrotik->id} - {$mikrotik->nama}",
            ]
        );

        Log::info("RADIUS: Synced Mikrotik {$mikrotik->nama} ({$mikrotik->ip_address}) to radnas");
    }

    /**
     * Hapus NAS dari radnas
     *
     * @param string $ipAddress IP Address Mikrotik untuk dihapus
     * @return void
     */
    public static function removeNas(string $ipAddress): void
    {
        self::where('nasname', $ipAddress)->delete();
        Log::info("RADIUS: Removed NAS {$ipAddress} from radnas");
    }

    // =============================================================
    // QUERY METHODS
    // =============================================================

    /**
     * Ambil NAS berdasarkan IP
     *
     * @param string $ip
     * @return self|null
     */
    public static function getByIp(string $ip): ?self
    {
        return self::where('nasname', $ip)->first();
    }

    /**
     * Ambil secret untuk NAS
     *
     * @param string $nasname
     * @return string|null
     */
    public static function getSecret(string $nasname): ?string
    {
        $nas = self::getByIp($nasname);
        return $nas ? $nas->secret : null;
    }

    /**
     * Ambil semua Mikrotik NAS
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getMikrotikNas(): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('type', self::TYPE_MIKROTIK)->get();
    }

    /**
     * Generate random secret
     *
     * @param int $length
     * @return string
     */
    public static function generateSecret(int $length = 32): string
    {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Get type label
     *
     * @return string
     */
    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }
}