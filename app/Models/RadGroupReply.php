<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * RadGroupReply Model
 *
 * FreeRADIUS radgroupreply table — group reply attributes
 *
 * Menyimpan reply attributes pada group level.
 * Digunakan untuk konfigurasi paket yang diterapkan ke semua
 * user dalam group tersebut.
 *
 * Contoh attributes per group (Hotspot Profile):
 * - Mikrotik-Queue-Max-Rate: Batas kecepatan upload/download
 * - Mikrotik-Queue-Limit: Queue limit
 * - Session-Timeout: Maksimum waktu session
 * - Idle-Timeout: Maksimum waktu idle
 * - Max-All-Session: Total waktu maximum
 * - Reply-Message: Pesan welcome
 *
 * Di Laravel FRRADIUS, tabel ini di-sync dari:
 * - HotspotProfile (saat dibuat/diubah/dihapus)
 * - PPPoEProfile (saat dibuat/diubah/dihapus)
 *
 * via RadiusSyncService::syncHotspotProfiles()
 */
class RadGroupReply extends Model
{
    /**
     * Tabel di database (FreeRADIUS table)
     */
    protected $table = 'radgroupreply';

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
        'groupname',
        'attribute',
        'op',
        'value',
    ];

    /**
     * Cast tipe data
     */
    protected $casts = [
        'groupname' => 'string',
        'attribute' => 'string',
        'op' => 'string',
        'value' => 'string',
    ];

    // =============================================================
    // CONSTANTS — Attribute Names
    // =============================================================

    const ATTR_MIKROTIK_QUEUE_MAX   = 'Mikrotik-Queue-Max-Rate';
    const ATTR_MIKROTIK_QUEUE_LIMIT = 'Mikrotik-Queue-Limit';
    const ATTR_SESSION_TIMEOUT      = 'Session-Timeout';
    const ATTR_IDLE_TIMEOUT         = 'Idle-Timeout';
    const ATTR_MAX_ALL_SESSION      = 'Max-All-Session';
    const ATTR_REPLY_MESSAGE        = 'Reply-Message';
    const ATTR_MIKROTIK_RATE_LIMIT  = 'Mikrotik-Rate-Limit';
    const ATTR_FRAMED_PROTOCOL      = 'Framed-Protocol';
    const ATTR_FRAMED_IP_NETMASK     = 'Framed-IP-Netmask';
    const ATTR_ACCT_INTERIM_INTERVAL = 'Acct-Interim-Interval';
    const ATTR_BANDWIDTH_NAME       = 'Mikrotik-Queue-Name';

    // =============================================================
    // SYNC METHODS — Hotspot Profiles
    // =============================================================

    /**
     * Sync hotspot profile ke radgroupreply
     *
     * Menghapus semua attributes lama untuk group ini,
     * lalu insert attributes baru berdasarkan konfigurasi profile.
     *
     * Dipanggil saat:
     * - HotspotProfile dibuat (Observer)
     * - HotspotProfile diubah (Observer)
     * - Batch sync via RadiusSyncService::syncHotspotProfiles()
     *
     * @param HotspotProfile $profile Hotspot profile dari Laravel
     * @return void
     */
    public static function syncHotspotProfile(HotspotProfile $profile): void
    {
        // Hapus semua attributes lama untuk group ini
        self::where('groupname', $profile->name)->delete();

        // Jika profile nonaktif, jangan insert attributes
        if ($profile->status !== 'active') {
            return;
        }

        // 1. Mikrotik Queue Max Rate (format: upload/download)
        // Contoh: "5M/5M" atau "10M/10M"
        if ($profile->rate_limit) {
            self::create([
                'groupname'  => $profile->name,
                'attribute'  => self::ATTR_MIKROTIK_QUEUE_MAX,
                'op'         => ':=',
                'value'      => $profile->rate_limit,
            ]);

            // 2. Mikrotik Queue Limit
            // Format: "5M/5M" (queue burst limit)
            self::create([
                'groupname'  => $profile->name,
                'attribute'  => self::ATTR_MIKROTIK_QUEUE_LIMIT,
                'op'         => ':=',
                'value'      => "{$profile->shared_users}M/{$profile->shared_users}M",
            ]);
        }

        // 3. Session Timeout (dalam detik)
        // Jika valid_for = 0, berarti unlimited
        if ($profile->valid_for > 0) {
            $sessionTimeout = $profile->valid_for * 60;

            self::create([
                'groupname'  => $profile->name,
                'attribute'  => self::ATTR_SESSION_TIMEOUT,
                'op'         => ':=',
                'value'      => (string) $sessionTimeout,
            ]);

            // 4. Max-All-Session (total waktu maximum)
            self::create([
                'groupname'  => $profile->name,
                'attribute'  => self::ATTR_MAX_ALL_SESSION,
                'op'         => ':=',
                'value'      => (string) $sessionTimeout,
            ]);
        }

        // 5. Idle Timeout (5 menit default)
        self::create([
            'groupname'  => $profile->name,
            'attribute'  => self::ATTR_IDLE_TIMEOUT,
            'op'         => ':=',
            'value'      => '300',
        ]);

        // 6. Reply Message
        self::create([
            'groupname'  => $profile->name,
            'attribute'  => self::ATTR_REPLY_MESSAGE,
            'op'         => ':=',
            'value'      => "Paket: {$profile->name}",
        ]);

        // 7. Acct-Interim-Interval (sync accounting setiap 5 menit)
        self::create([
            'groupname'  => $profile->name,
            'attribute'  => self::ATTR_ACCT_INTERIM_INTERVAL,
            'op'         => ':=',
            'value'      => '300',
        ]);
    }

    /**
     * Sync PPPoE profile ke radgroupreply
     *
     * @param PPPoEProfile $profile PPPoE profile dari Laravel
     * @return void
     */
    public static function syncPPPoEProfile(PPPoEProfile $profile): void
    {
        // Hapus semua attributes lama
        self::where('groupname', $profile->group)->delete();

        if ($profile->status !== 'active') {
            return;
        }

        $groupName = $profile->group ?? 'FRRADIUS';

        // 1. Mikrotik Rate Limit
        if ($profile->rate_limit) {
            self::create([
                'groupname'  => $groupName,
                'attribute'  => self::ATTR_MIKROTIK_RATE_LIMIT,
                'op'         => ':=',
                'value'      => $profile->rate_limit,
            ]);
        }

        // 2. Framed Protocol (PPP untuk PPPoE)
        self::create([
            'groupname'  => $groupName,
            'attribute'  => self::ATTR_FRAMED_PROTOCOL,
            'op'         => ':=',
            'value'      => 'PPP',
        ]);

        // 3. Framed IP Netmask
        self::create([
            'groupname'  => $groupName,
            'attribute'  => self::ATTR_FRAMED_IP_NETMASK,
            'op'         => ':=',
            'value'      => '255.255.255.0',
        ]);

        // 4. Reply Message
        self::create([
            'groupname'  => $groupName,
            'attribute'  => self::ATTR_REPLY_MESSAGE,
            'op'         => ':=',
            'value'      => "Paket: {$profile->name}",
        ]);
    }

    // =============================================================
    // HELPER METHODS
    // =============================================================

    /**
     * Hapus semua group attributes untuk group tertentu
     *
     * @param string $groupname
     * @return void
     */
    public static function removeGroup(string $groupname): void
    {
        self::where('groupname', $groupname)->delete();
    }

    /**
     * Ambil semua attributes untuk group tertentu
     *
     * @param string $groupname
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getGroupAttributes(string $groupname): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('groupname', $groupname)->get();
    }

    /**
     * Ambil rate limit untuk group
     *
     * @param string $groupname
     * @return string|null
     */
    public static function getGroupRateLimit(string $groupname): ?string
    {
        $reply = self::where('groupname', $groupname)
            ->where('attribute', self::ATTR_MIKROTIK_QUEUE_MAX)
            ->first();

        if (!$reply) {
            $reply = self::where('groupname', $groupname)
                ->where('attribute', self::ATTR_MIKROTIK_RATE_LIMIT)
                ->first();
        }

        return $reply ? $reply->value : null;
    }

    /**
     * Cek apakah group memiliki attributes
     *
     * @param string $groupname
     * @return bool
     */
    public static function groupHasAttributes(string $groupname): bool
    {
        return self::where('groupname', $groupname)->exists();
    }
}