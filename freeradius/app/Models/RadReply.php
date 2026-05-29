<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * RadReply Model
 *
 * FreeRADIUS radreply table — server response attributes
 *
 * Digunakan untuk menyimpan reply attributes yang akan dikirim
 * ke NAS (Mikrotik) setelah authentication success.
 *
 * Contoh attributes:
 * - Session-Timeout: Maksimum waktu session (dalam detik)
 * - Idle-Timeout: Maksimum waktu idle sebelum disconnect
 * - Reply-Message: Pesan yang ditampilkan ke user
 * - Mikrotik-Rate-Limit: Batas kecepatan upload/download
 * - Framed-IP-Address: IP statis untuk user
 * - Framed-Protocol: Protocol (PPP untuk PPPoE)
 *
 * Di Laravel FRRADIUS, tabel ini di-sync via RadReply::syncHotspotUser()
 * dan RadReply::syncPPPoEUser() saat user dibuat/diubah.
 */
class RadReply extends Model
{
    /**
     * Tabel di database (FreeRADIUS table)
     */
    protected $table = 'radreply';

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
        'username',
        'attribute',
        'op',
        'value',
    ];

    /**
     * Cast tipe data
     */
    protected $casts = [
        'username' => 'string',
        'attribute' => 'string',
        'op' => 'string',
        'value' => 'string',
    ];

    // =============================================================
    // CONSTANTS — Attribute Names
    // =============================================================

    const ATTR_SESSION_TIMEOUT      = 'Session-Timeout';
    const ATTR_IDLE_TIMEOUT         = 'Idle-Timeout';
    const ATTR_REPLY_MESSAGE        = 'Reply-Message';
    const ATTR_MIKROTIK_RATE_LIMIT  = 'Mikrotik-Rate-Limit';
    const ATTR_MIKROTIK_QUEUE_MAX   = 'Mikrotik-Queue-Max-Rate';
    const ATTR_MIKROTIK_QUEUE_LIMIT = 'Mikrotik-Queue-Limit';
    const ATTR_FRAMED_IP_ADDRESS    = 'Framed-IP-Address';
    const ATTR_FRAMED_PROTOCOL      = 'Framed-Protocol';
    const ATTR_FRAMED_NETMASK       = 'Framed-IP-Netmask';
    const ATTR_GROUP                = 'Group';
    const ATTR_SERVICE_TYPE         = 'Service-Type';
    const ATTR_PORT_LIMIT           = 'Port-Limit';
    const ATTR_ACCT_INTERIM_INTERVAL = 'Acct-Interim-Interval';

    // =============================================================
    // SYNC METHODS — Hotspot Users
    // =============================================================

    /**
     * Sync reply attributes untuk hotspot user
     *
     * Mengisi tabel radreply dengan attributes berdasarkan paket profile.
     *
     * @param HotspotUser $user Hotspot user dari Laravel
     * @return void
     */
    public static function syncHotspotUser(HotspotUser $user): void
    {
        // Hapus semua entry lama untuk username ini
        self::where('username', $user->username)->delete();

        $profile = $user->profile;
        if (!$profile) return;

        // Jika user disabled, jangan insert attributes
        if ($user->status === HotspotUser::STATUS_DISABLED) {
            return;
        }

        // 1. Mikrotik Rate Limit (format: upload/download, e.g., "10M/10M")
        if ($profile->rate_limit) {
            self::createAttribute([
                'username'   => $user->username,
                'attribute'  => self::ATTR_MIKROTIK_QUEUE_MAX,
                'op'         => '=',
                'value'      => $profile->rate_limit,
            ]);
        }

        // 2. Session Timeout (dalam detik)
        // Jika valid_for = 0, berarti unlimited
        if ($profile->valid_for > 0) {
            $sessionTimeout = $profile->valid_for * 60; // Convert menit ke detik
            self::createAttribute([
                'username'   => $user->username,
                'attribute'  => self::ATTR_SESSION_TIMEOUT,
                'op'         => '=',
                'value'      => (string) $sessionTimeout,
            ]);
        }

        // 3. Idle Timeout (5 menit default)
        // User akan disconnect jika tidak ada aktivitas selama 5 menit
        self::createAttribute([
            'username'   => $user->username,
            'attribute'  => self::ATTR_IDLE_TIMEOUT,
            'op'         => '=',
            'value'      => '300',
        ]);

        // 4. Max-All-Session (total waktu maximum)
        if ($profile->valid_for > 0) {
            self::createAttribute([
                'username'   => $user->username,
                'attribute'  => self::ATTR_MAX_ALL_SESSION,
                'op'         => ':=',
                'value'      => (string) ($profile->valid_for * 60),
            ]);
        }

        // 5. Reply Message (nama paket)
        self::createAttribute([
            'username'   => $user->username,
            'attribute'  => self::ATTR_REPLY_MESSAGE,
            'op'         => '=',
            'value'      => "Selamat datang {$user->username}! Paket: {$profile->name}",
        ]);

        // 6. Acct-Interim-Interval (sync accounting setiap 5 menit)
        self::createAttribute([
            'username'   => $user->username,
            'attribute'  => self::ATTR_ACCT_INTERIM_INTERVAL,
            'op'         => '=',
            'value'      => '300',
        ]);
    }

    /**
     * Sync reply attributes untuk PPPoE user
     *
     * @param PPPoEUser $user PPPoE user dari Laravel
     * @return void
     */
    public static function syncPPPoEUser(PPPoEUser $user): void
    {
        // Hapus semua entry lama untuk username ini
        self::where('username', $user->username)->delete();

        $profile = $user->profile;
        if (!$profile) return;

        // Jika user inactive, jangan insert attributes
        if ($user->status === PPPoEUser::STATUS_INACTIVE) {
            return;
        }

        // 1. Mikrotik Rate Limit
        if ($profile->rate_limit) {
            self::createAttribute([
                'username'   => $user->username,
                'attribute'  => self::ATTR_MIKROTIK_RATE_LIMIT,
                'op'         => '=',
                'value'      => $profile->rate_limit,
            ]);
        }

        // 2. Framed Protocol (PPP untuk PPPoE)
        self::createAttribute([
            'username'   => $user->username,
            'attribute'  => self::ATTR_FRAMED_PROTOCOL,
            'op'         => '=',
            'value'      => 'PPP',
        ]);

        // 3. Group (nama paket/group PPPoE)
        self::createAttribute([
            'username'   => $user->username,
            'attribute'  => self::ATTR_GROUP,
            'op'         => '=',
            'value'      => $profile->group ?? 'FRRADIUS',
        ]);

        // 4. Static IP Address (jika ada)
        if ($user->ip_address) {
            self::createAttribute([
                'username'   => $user->username,
                'attribute'  => self::ATTR_FRAMED_IP_ADDRESS,
                'op'         => '=',
                'value'      => $user->ip_address,
            ]);
        }

        // 5. Framed IP Netmask
        self::createAttribute([
            'username'   => $user->username,
            'attribute'  => self::ATTR_FRAMED_NETMASK,
            'op'         => '=',
            'value'      => '255.255.255.0',
        ]);

        // 6. Port Limit (limit concurrent connections)
        self::createAttribute([
            'username'   => $user->username,
            'attribute'  => self::ATTR_PORT_LIMIT,
            'op'         => '=',
            'value'      => '2',
        ]);

        // 7. Reply Message
        self::createAttribute([
            'username'   => $user->username,
            'attribute'  => self::ATTR_REPLY_MESSAGE,
            'op'         => '=',
            'value'      => "Selamat datang {$user->fullname}! Paket: {$profile->name}",
        ]);
    }

    // =============================================================
    // HELPER METHODS
    // =============================================================

    /**
     * Create attribute helper
     *
     * @param array $data
     * @return self
     */
    private static function createAttribute(array $data): self
    {
        $model = new self();
        $model->fill($data);
        $model->save();
        return $model;
    }

    /**
     * Hapus semua reply attributes untuk user
     *
     * @param string $username
     * @return void
     */
    public static function removeUser(string $username): void
    {
        self::where('username', $username)->delete();
    }

    /**
     * Ambil semua reply attributes untuk user
     *
     * @param string $username
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getUserAttributes(string $username): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('username', $username)->get();
    }

    /**
     * Ambil attribute tertentu untuk user
     *
     * @param string $username
     * @param string $attribute
     * @return string|null
     */
    public static function getAttributeValue(string $username, string $attribute): ?string
    {
        $reply = self::where('username', $username)
            ->where('attribute', $attribute)
            ->first();

        return $reply ? $reply->value : null;
    }
}

// Tambah Max-All-Session constant di RadCheck jika belum ada
namespace App\Models;

if (!defined('App\Models\RadCheck::ATTR_MAX_ALL_SESSION')) {
    // Constant sudah di-declare di RadCheck.php
}