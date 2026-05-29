<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * RadUserGroup Model
 *
 * FreeRADIUS radusergroup table — user to group mapping
 *
 * Menyimpan mapping antara username dan groupname.
 * FreeRADIUS menggunakan ini untuk menentukan:
 * - Paket/user profile (hotspot_profiles.name)
 * - Group attributes dari radgroupreply
 *
 * Di Laravel FRRADIUS:
 * - groupname = nama paket hotspot (e.g., "1 Hari - 10 Mbps")
 * - Atau group PPPoE (e.g., "FRRADIUS", "PREMIUM")
 *
 * NOTE: Tabel ini di-sync dari HotspotUser.profile dan PPPoEUser.package
 */
class RadUserGroup extends Model
{
    /**
     * Tabel di database (FreeRADIUS table)
     */
    protected $table = 'radusergroup';

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
        'groupname',
        'priority',
    ];

    /**
     * Cast tipe data
     */
    protected $casts = [
        'username' => 'string',
        'groupname' => 'string',
        'priority' => 'integer',
    ];

    // =============================================================
    // SYNC METHODS
    // =============================================================

    /**
     * Assign user ke paket/group
     *
     * Dipanggil saat:
     * - HotspotUser dibuat/diubah → assign ke hotspot profile name
     * - PPPoEUser dibuat/diubah → assign ke PPPoE profile group
     *
     * @param string $username Username
     * @param string $groupname Nama group/paket
     * @param int $priority Prioritas (default: 1)
     * @return void
     */
    public static function assignUser(string $username, string $groupname, int $priority = 1): void
    {
        self::updateOrCreate(
            ['username' => $username],
            [
                'groupname' => $groupname,
                'priority'  => $priority,
            ]
        );
    }

    /**
     * Assign hotspot user ke profile group
     *
     * @param HotspotUser $user
     * @return void
     */
    public static function assignHotspotUser(HotspotUser $user): void
    {
        if ($user->status === HotspotUser::STATUS_DISABLED) {
            self::removeUser($user->username);
            return;
        }

        $profile = $user->profile;
        if (!$profile) return;

        self::assignUser($user->username, $profile->name);
    }

    /**
     * Assign PPPoE user ke profile group
     *
     * @param PPPoEUser $user
     * @return void
     */
    public static function assignPPPoEUser(PPPoEUser $user): void
    {
        if ($user->status === PPPoEUser::STATUS_INACTIVE) {
            self::removeUser($user->username);
            return;
        }

        $profile = $user->profile;
        if (!$profile) return;

        $groupName = $profile->group ?? 'FRRADIUS';
        self::assignUser($user->username, $groupName);
    }

    // =============================================================
    // QUERY METHODS
    // =============================================================

    /**
     * Hapus user dari semua group
     *
     * @param string $username
     * @return void
     */
    public static function removeUser(string $username): void
    {
        self::where('username', $username)->delete();
    }

    /**
     * Cek apakah user ada di group tertentu
     *
     * @param string $username
     * @param string $groupname
     * @return bool
     */
    public static function isInGroup(string $username, string $groupname): bool
    {
        return self::where('username', $username)
            ->where('groupname', $groupname)
            ->exists();
    }

    /**
     * Ambil semua user dalam group tertentu
     *
     * @param string $groupname
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getUsersInGroup(string $groupname): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('groupname', $groupname)->get();
    }

    /**
     * Ambil semua group untuk user tertentu
     *
     * @param string $username
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getUserGroups(string $username): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('username', $username)
            ->orderBy('priority')
            ->get();
    }

    /**
     * Ambil groupname utama user (prioritas tertinggi)
     *
     * @param string $username
     * @return string|null
     */
    public static function getPrimaryGroup(string $username): ?string
    {
        $group = self::where('username', $username)
            ->orderBy('priority')
            ->first();

        return $group ? $group->groupname : null;
    }

    /**
     * Hitung jumlah user dalam group
     *
     * @param string $groupname
     * @return int
     */
    public static function countUsersInGroup(string $groupname): int
    {
        return self::where('groupname', $groupname)->count();
    }
}