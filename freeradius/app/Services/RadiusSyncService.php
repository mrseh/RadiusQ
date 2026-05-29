<?php

namespace App\Services;

use App\Models\HotspotUser;
use App\Models\PPPoEUser;
use App\Models\HotspotProfile;
use App\Models\PPPoEProfile;
use App\Models\Nas;
use App\Models\RadCheck;
use App\Models\RadReply;
use App\Models\RadUserGroup;
use App\Models\RadNas;
use App\Models\RadGroupReply;
use Illuminate\Support\Facades\Log;

/**
 * RadiusSyncService
 *
 * Service utama untuk meng-sync data dari Laravel ke FreeRADIUS tables.
 *
 * Alur sync:
 * 1. User dibuat/diubah di Laravel (HotspotUser / PPPoEUser)
 * 2. Observer menangkap event (created/updated/deleted)
 * 3. Observer memanggil method yang sesuai di service ini
 * 4. Service mengupdate radcheck, radreply, radusergroup
 *
 * Batch sync:
 * - syncAll() → sync semua profiles, NAS, dan users
 * - syncHotspotProfiles() → sync semua hotspot profiles
 * - syncNasDevices() → sync semua NAS
 *
 * CRON JOBS:
 * - Setiap 1 menit: php artisan radius:sync-sessions
 * - Setiap 5 menit: syncAll()
 */
class RadiusSyncService
{
    // =============================================================
    // FULL SYNC — Semua data
    // =============================================================

    /**
     * Sync semua data dari Laravel ke FreeRADIUS tables
     *
     * Methode ini dipanggil oleh:
     * - Cron job setiap 5 menit
     * - Artisan command: php artisan radius:sync-all
     *
     * @return void
     */
    public function syncAll(): void
    {
        $this->syncHotspotProfiles();
        $this->syncPPPoEProfiles();
        $this->syncNasDevices();
        $this->syncAllHotspotUsers();
        $this->syncAllPPPoEUsers();

        Log::info('RADIUS: Full sync completed', [
            'profiles' => HotspotProfile::count() + PPPoEProfile::count(),
            'nas' => Nas::count(),
            'hotspot_users' => HotspotUser::count(),
            'pppoe_users' => PPPoEUser::count(),
        ]);
    }

    // =============================================================
    // PROFILE SYNC — Hotspot Profiles
    // =============================================================

    /**
     * Sync semua hotspot profiles ke radgroupreply
     *
     * @return void
     */
    public function syncHotspotProfiles(): void
    {
        $profiles = HotspotProfile::with([])->get();

        foreach ($profiles as $profile) {
            try {
                RadGroupReply::syncHotspotProfile($profile);
            } catch (\Exception $e) {
                Log::error("RADIUS: Failed to sync hotspot profile {$profile->name}: {$e->getMessage()}");
            }
        }

        Log::info("RADIUS: Synced {$profiles->count()} hotspot profiles to radgroupreply");
    }

    /**
     * Sync satu hotspot profile
     *
     * @param HotspotProfile $profile
     * @return void
     */
    public function syncHotspotProfile(HotspotProfile $profile): void
    {
        try {
            RadGroupReply::syncHotspotProfile($profile);
            Log::info("RADIUS: Synced hotspot profile {$profile->name}");
        } catch (\Exception $e) {
            Log::error("RADIUS: Failed to sync hotspot profile {$profile->name}: {$e->getMessage()}");
        }
    }

    /**
     * Hapus hotspot profile dari radgroupreply
     *
     * @param string $profileName
     * @return void
     */
    public function removeHotspotProfile(string $profileName): void
    {
        RadGroupReply::where('groupname', $profileName)->delete();
        Log::info("RADIUS: Removed hotspot profile {$profileName} from radgroupreply");
    }

    // =============================================================
    // PROFILE SYNC — PPPoE Profiles
    // =============================================================

    /**
     * Sync semua PPPoE profiles ke radgroupreply
     *
     * @return void
     */
    public function syncPPPoEProfiles(): void
    {
        $profiles = PPPoEProfile::with([])->get();

        foreach ($profiles as $profile) {
            try {
                RadGroupReply::syncPPPoEProfile($profile);
            } catch (\Exception $e) {
                Log::error("RADIUS: Failed to sync PPPoE profile {$profile->name}: {$e->getMessage()}");
            }
        }

        Log::info("RADIUS: Synced {$profiles->count()} PPPoE profiles to radgroupreply");
    }

    /**
     * Sync satu PPPoE profile
     *
     * @param PPPoEProfile $profile
     * @return void
     */
    public function syncPPPoEProfile(PPPoEProfile $profile): void
    {
        try {
            RadGroupReply::syncPPPoEProfile($profile);
            Log::info("RADIUS: Synced PPPoE profile {$profile->name}");
        } catch (\Exception $e) {
            Log::error("RADIUS: Failed to sync PPPoE profile {$profile->name}: {$e->getMessage()}");
        }
    }

    // =============================================================
    // NAS SYNC — Network Access Server
    // =============================================================

    /**
     * Sync semua NAS/Mikrotik ke radnas
     *
     * @return void
     */
    public function syncNasDevices(): void
    {
        $nasDevices = Nas::whereNotNull('ip_router')->get();

        foreach ($nasDevices as $nas) {
            try {
                RadNas::syncFromNas($nas);
            } catch (\Exception $e) {
                Log::error("RADIUS: Failed to sync NAS {$nas->name}: {$e->getMessage()}");
            }
        }

        Log::info("RADIUS: Synced {$nasDevices->count()} NAS devices to radnas");
    }

    /**
     * Sync satu NAS
     *
     * @param Nas $nas
     * @return void
     */
    public function syncNas(Nas $nas): void
    {
        try {
            RadNas::syncFromNas($nas);
        } catch (\Exception $e) {
            Log::error("RADIUS: Failed to sync NAS {$nas->name}: {$e->getMessage()}");
        }
    }

    /**
     * Hapus NAS dari radnas
     *
     * @param string $ipRouter
     * @return void
     */
    public function removeNas(string $ipRouter): void
    {
        RadNas::removeNas($ipRouter);
    }

    // =============================================================
    // HOTSPOT USER SYNC
    // =============================================================

    /**
     * Sync semua hotspot users ke rad tables
     *
     * @return void
     */
    public function syncAllHotspotUsers(): void
    {
        $users = HotspotUser::where('status', '!=', HotspotUser::STATUS_DISABLED)
            ->with('profile')
            ->get();

        foreach ($users as $user) {
            try {
                $this->syncHotspotUser($user);
            } catch (\Exception $e) {
                Log::error("RADIUS: Failed to sync hotspot user {$user->username}: {$e->getMessage()}");
            }
        }

        Log::info("RADIUS: Synced {$users->count()} hotspot users to rad tables");
    }

    /**
     * Sync satu hotspot user ke FreeRADIUS
     *
     * Meng-update 3 tabel:
     * - radcheck: username + password
     * - radreply: rate-limit, session-timeout, dll
     * - radusergroup: groupname (paket)
     *
     * Dipanggil oleh HotspotUserObserver saat user dibuat/diubah.
     *
     * @param HotspotUser $user
     * @return void
     */
    public function syncHotspotUser(HotspotUser $user): void
    {
        // Cek apakah user active
        if ($user->status === HotspotUser::STATUS_DISABLED) {
            // User disabled → hapus dari RADIUS
            $this->removeUser($user->username);
            return;
        }

        // Sync ke radcheck (password + simultaneous-use)
        RadCheck::syncHotspotUser($user);

        // Sync ke radreply (attributes)
        RadReply::syncHotspotUser($user);

        // Sync ke radusergroup (groupname/paket)
        $profile = $user->profile;
        if ($profile) {
            RadUserGroup::assignUser($user->username, $profile->name);
        }

        Log::debug("RADIUS: Synced hotspot user {$user->username}");
    }

    /**
     * Sync beberapa hotspot users
     *
     * @param array $userIds
     * @return void
     */
    public function syncHotspotUsers(array $userIds): void
    {
        $users = HotspotUser::whereIn('id', $userIds)
            ->with('profile')
            ->get();

        foreach ($users as $user) {
            $this->syncHotspotUser($user);
        }
    }

    // =============================================================
    // PPPoE USER SYNC
    // =============================================================

    /**
     * Sync semua PPPoE users ke rad tables
     *
     * @return void
     */
    public function syncAllPPPoEUsers(): void
    {
        $users = PPPoEUser::where('status', '!=', PPPoEUser::STATUS_INACTIVE)
            ->with('profile')
            ->get();

        foreach ($users as $user) {
            try {
                $this->syncPPPoEUser($user);
            } catch (\Exception $e) {
                Log::error("RADIUS: Failed to sync PPPoE user {$user->username}: {$e->getMessage()}");
            }
        }

        Log::info("RADIUS: Synced {$users->count()} PPPoE users to rad tables");
    }

    /**
     * Sync satu PPPoE user ke FreeRADIUS
     *
     * Meng-update 3 tabel:
     * - radcheck: username + password
     * - radreply: rate-limit, static-ip, dll
     * - radusergroup: groupname (PPPoE group)
     *
     * Dipanggil oleh PPPoEUserObserver saat user dibuat/diubah.
     *
     * @param PPPoEUser $user
     * @return void
     */
    public function syncPPPoEUser(PPPoEUser $user): void
    {
        // Cek apakah user active
        if ($user->status === PPPoEUser::STATUS_INACTIVE) {
            // User inactive → hapus dari RADIUS
            $this->removeUser($user->username);
            return;
        }

        // Sync ke radcheck (password + simultaneous-use)
        RadCheck::syncPPPoEUser($user);

        // Sync ke radreply (attributes)
        RadReply::syncPPPoEUser($user);

        // Sync ke radusergroup (groupname/paket)
        $profile = $user->profile;
        if ($profile) {
            $groupName = $profile->group ?? 'FRRADIUS';
            RadUserGroup::assignUser($user->username, $groupName);
        }

        Log::debug("RADIUS: Synced PPPoE user {$user->username}");
    }

    /**
     * Sync beberapa PPPoE users
     *
     * @param array $userIds
     * @return void
     */
    public function syncPPPoEUsers(array $userIds): void
    {
        $users = PPPoEUser::whereIn('id', $userIds)
            ->with('profile')
            ->get();

        foreach ($users as $user) {
            $this->syncPPPoEUser($user);
        }
    }

    /**
     * Suspend PPPoE user (disable tanpa hapus dari database)
     *
     * @param string $username
     * @param bool $suspend true=suspend, false=unsuspend
     * @return void
     */
    public function suspendUser(string $username, bool $suspend = true): void
    {
        if ($suspend) {
            // Hapus dari radcheck → user tidak bisa login
            RadCheck::removeUser($username);
            RadReply::removeUser($username);
            RadUserGroup::removeUser($username);
            Log::info("RADIUS: Suspended user {$username}");
        } else {
            // Re-enable: cari user di database dan re-sync
            // Ini butuh lookup ke tabel Laravel
            $hotspotUser = HotspotUser::where('username', $username)->first();
            if ($hotspotUser) {
                $this->syncHotspotUser($hotspotUser);
            } else {
                $pppoeUser = PPPoEUser::where('username', $username)->first();
                if ($pppoeUser) {
                    $this->syncPPPoEUser($pppoeUser);
                }
            }
            Log::info("RADIUS: Unsuspended user {$username}");
        }
    }

    // =============================================================
    // REMOVE USER
    // =============================================================

    /**
     * Hapus user dari semua FreeRADIUS tables
     *
     * Menghapus dari:
     * - radcheck
     * - radreply
     * - radusergroup
     *
     * Dipanggil saat:
     * - User dihapus (deleted event)
     * - User dinonaktifkan (updated event, status=disabled/inactive)
     *
     * @param string $username
     * @return void
     */
    public function removeUser(string $username): void
    {
        RadCheck::removeUser($username);
        RadReply::removeUser($username);
        RadUserGroup::removeUser($username);

        Log::info("RADIUS: Removed user {$username} from all RADIUS tables");
    }

    /**
     * Remove user by type
     *
     * @param string $username
     * @param string $type 'hotspot' or 'pppoe'
     * @return void
     */
    public function removeUserByType(string $username, string $type): void
    {
        $this->removeUser($username);
        Log::info("RADIUS: Removed {$type} user {$username}");
    }

    // =============================================================
    // BATCH OPERATIONS
    // =============================================================

    /**
     * Resync semua users dalam paket tertentu
     *
     * @param string $profileName Nama paket/profile
     * @param string $type 'hotspot' or 'pppoe'
     * @return int Jumlah user yang di-sync
     */
    public function resyncUsersInProfile(string $profileName, string $type = 'hotspot'): int
    {
        $count = 0;

        if ($type === 'hotspot') {
            $users = HotspotUser::whereHas('profile', function ($q) use ($profileName) {
                $q->where('name', $profileName);
            })->where('status', '!=', HotspotUser::STATUS_DISABLED)->get();

            foreach ($users as $user) {
                $this->syncHotspotUser($user);
                $count++;
            }
        } else {
            $users = PPPoEUser::whereHas('profile', function ($q) use ($profileName) {
                $q->where('group', $profileName);
            })->where('status', '!=', PPPoEUser::STATUS_INACTIVE)->get();

            foreach ($users as $user) {
                $this->syncPPPoEUser($user);
                $count++;
            }
        }

        Log::info("RADIUS: Resynced {$count} users in profile {$profileName}");
        return $count;
    }

    /**
     * Resync semua users di NAS tertentu
     *
     * @param int $nasId
     * @return int Jumlah user yang di-sync
     */
    public function resyncUsersInNas(int $nasId): int
    {
        $nas = Nas::find($nasId);
        if (!$nas) return 0;

        $count = 0;

        // Hotspot users
        $hotspotUsers = HotspotUser::where('nas', $nas->id)
            ->orWhere('nas', 'all')
            ->where('status', '!=', HotspotUser::STATUS_DISABLED)
            ->with('profile')
            ->get();

        foreach ($hotspotUsers as $user) {
            $this->syncHotspotUser($user);
            $count++;
        }

        // PPPoE users
        $pppoeUsers = PPPoEUser::where('nas_id', $nasId)
            ->where('status', '!=', PPPoEUser::STATUS_INACTIVE)
            ->with('profile')
            ->get();

        foreach ($pppoeUsers as $user) {
            $this->syncPPPoEUser($user);
            $count++;
        }

        Log::info("RADIUS: Resynced {$count} users in NAS {$nas->name}");
        return $count;
    }

    // =============================================================
    // VERIFICATION & DEBUG
    // =============================================================

    /**
     * Verifikasi apakah user ada di semua tabel yang diperlukan
     *
     * @param string $username
     * @return array ['radcheck' => bool, 'radreply' => bool, 'radusergroup' => bool]
     */
    public function verifyUser(string $username): array
    {
        return [
            'radcheck'     => RadCheck::where('username', $username)->exists(),
            'radreply'     => RadReply::where('username', $username)->exists(),
            'radusergroup' => RadUserGroup::where('username', $username)->exists(),
        ];
    }

    /**
     * Get user info dari semua FreeRADIUS tables
     *
     * @param string $username
     * @return array
     */
    public function getUserInfo(string $username): array
    {
        return [
            'radcheck' => RadCheck::where('username', $username)->get()->toArray(),
            'radreply' => RadReply::where('username', $username)->get()->toArray(),
            'radusergroup' => RadUserGroup::where('username', $username)->get()->toArray(),
        ];
    }

    /**
     * Count records di setiap tabel
     *
     * @return array
     */
    public function getStats(): array
    {
        return [
            'radcheck' => RadCheck::count(),
            'radreply' => RadReply::count(),
            'radusergroup' => RadUserGroup::count(),
            'radgroupreply' => RadGroupReply::count(),
            'radnas' => RadNas::count(),
        ];
    }

    /**
     * Cleanup orphan entries (username yang tidak ada di tabel user Laravel)
     *
     * @return int Jumlah entries yang dihapus
     */
    public function cleanupOrphans(): int
    {
        $count = 0;

        // Get all usernames dari radcheck
        $radUsernames = RadCheck::distinct()->pluck('username')->toArray();

        foreach ($radUsernames as $username) {
            // Cek apakah ada di HotspotUser atau PPPoEUser
            $existsInHotspot = HotspotUser::where('username', $username)->exists();
            $existsInPPPoE = PPPoEUser::where('username', $username)->exists();

            if (!$existsInHotspot && !$existsInPPPoE) {
                $this->removeUser($username);
                $count++;
            }
        }

        Log::info("RADIUS: Cleaned up {$count} orphan entries");
        return $count;
    }
}