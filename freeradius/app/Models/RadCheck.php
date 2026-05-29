<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * RadCheck Model
 *
 * FreeRADIUS radcheck table — stored user credentials (password)
 *
 * Attributes:
 * - username: Username untuk login Hotspot/PPPoE
 * - attribute: Tipe attribute (Cleartext-Password, Crypt-Password, Simultaneous-Use)
 * - op: Operator (:= for set, = for assignment)
 * - value: Nilai attribute (password string atau number)
 *
 * Di Laravel FRRADIUS, tabel ini di-sync via RadCheck::syncHotspotUser()
 * dan RadCheck::syncPPPoEUser() saat user dibuat/diubah.
 */
class RadCheck extends Model
{
    /**
     * Tabel di database (FreeRADIUS table)
     */
    protected $table = 'radcheck';

    /**
     * Primary key
     */
    protected $primaryKey = 'id';

    /**
     * Tidak pakai timestamps Laravel (tabel FreeRADIUS tidak ada created_at/updated_at)
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
    // CONSTANTS — Attribute Names (RFC 2865, RFC 2869)
    // =============================================================

    const ATTR_CLEAR_TEXT_PASSWORD = 'Cleartext-Password';
    const ATTR_CRYPT_PASSWORD      = 'Crypt-Password';
    const ATTR_USER_PASSWORD        = 'User-Password';
    const ATTR_SIMULTANEOUS_USE    = 'Simultaneous-Use';
    const ATTR_MAX_ALL_SESSION      = 'Max-All-Session';
    const ATTR_LOGIN_TIME           = 'Login-Time';
    const ATTR_EXPIRATION           = 'Expiration';

    // =============================================================
    // SYNC METHODS — Hotspot Users
    // =============================================================

    /**
     * Sync hotspot user ke radcheck
     *
     * Dipanggil saat:
     * - HotspotUser dibuat (Observer::created)
     * - HotspotUser diubah (Observer::updated)
     *
     * @param HotspotUser $user Hotspot user dari Laravel
     * @return void
     */
    public static function syncHotspotUser(HotspotUser $user): void
    {
        // Hapus semua entry lama untuk username ini
        self::where('username', $user->username)->delete();

        // Jika user di-disabled, jangan insert ke radcheck
        if ($user->status === HotspotUser::STATUS_DISABLED) {
            return;
        }

        // 1. Insert Password (Cleartext-Password)
        // Mikrotik Hotspot butuh cleartext password
        self::create([
            'username'   => $user->username,
            'attribute'  => self::ATTR_CLEAR_TEXT_PASSWORD,
            'op'         => ':=',
            'value'      => $user->password,
        ]);

        // 2. Simultaneous-Connect (max concurrent login)
        // Jika paket mengizinkan >1 user login bersamaan (shared voucher)
        $profile = $user->profile;
        if ($profile && $profile->shared_users > 1) {
            self::create([
                'username'   => $user->username,
                'attribute'  => self::ATTR_SIMULTANEOUS_USE,
                'op'         => ':=',
                'value'      => (string) $profile->shared_users,
            ]);
        }
    }

    /**
     * Sync PPPoE user ke radcheck
     *
     * Dipanggil saat:
     * - PPPoEUser dibuat (Observer::created)
     * - PPPoEUser diubah (Observer::updated)
     *
     * @param PPPoEUser $user PPPoE user dari Laravel
     * @return void
     */
    public static function syncPPPoEUser(PPPoEUser $user): void
    {
        // Hapus semua entry lama untuk username ini
        self::where('username', $user->username)->delete();

        // Jika user inactive, jangan insert ke radcheck
        if ($user->status === PPPoEUser::STATUS_INACTIVE) {
            return;
        }

        // 1. Insert Password (Cleartext-Password)
        // PPPoE juga menggunakan cleartext untuk Mikrotik
        self::create([
            'username'   => $user->username,
            'attribute'  => self::ATTR_CLEAR_TEXT_PASSWORD,
            'op'         => ':=',
            'value'      => $user->password,
        ]);

        // 2. Simultaneous-Use = 1 (MAC binding)
        // Untuk PPPoE, hanya 1 session per username
        if ($user->mac_address) {
            self::create([
                'username'   => $user->username,
                'attribute'  => self::ATTR_SIMULTANEOUS_USE,
                'op'         => ':=',
                'value'      => '1',
            ]);
        }
    }

    // =============================================================
    // SYNC METHODS — Delete User
    // =============================================================

    /**
     * Hapus user dari radcheck
     *
     * Dipanggil saat user dihapus atau dinonaktifkan
     *
     * @param string $username Username untuk dihapus
     * @return void
     */
    public static function removeUser(string $username): void
    {
        self::where('username', $username)->delete();
    }

    /**
     * Cek apakah user ada di radcheck
     *
     * @param string $username
     * @return bool
     */
    public static function userExists(string $username): bool
    {
        return self::where('username', $username)->exists();
    }

    /**
     * Ambil password user dari radcheck
     *
     * @param string $username
     * @return string|null Password atau null jika tidak ditemukan
     */
    public static function getPassword(string $username): ?string
    {
        $check = self::where('username', $username)
            ->where('attribute', self::ATTR_CLEAR_TEXT_PASSWORD)
            ->first();

        return $check ? $check->value : null;
    }
}
