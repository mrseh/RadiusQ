<?php

namespace App\Observers;

use App\Models\HotspotUser;
use App\Services\RadiusSyncService;
use Illuminate\Support\Facades\Log;

/**
 * HotspotUserObserver
 *
 * Observer untuk HotspotUser model.
 * Otomatis sync ke FreeRADIUS tables saat user dibuat/diubah/dihapus.
 *
 * Events:
 * - created: Sync new user ke radcheck, radreply, radusergroup
 * - updated: Update/remove user sesuai perubahan
 * - deleted: Hapus user dari semua tabel RADIUS
 *
 * Usage:
 * Daftarkan di AppServiceProvider:
 *   HotspotUser::observe(HotspotUserObserver::class);
 */
class HotspotUserObserver
{
    /**
     * RadiusSyncService instance
     */
    private RadiusSyncService $radiusSync;

    /**
     * Constructor — inject RadiusSyncService via DI
     */
    public function __construct(RadiusSyncService $radiusSync)
    {
        $this->radiusSync = $radiusSync;
    }

    /**
     * Handle the HotspotUser "created" event.
     *
     * Dipanggil saat user hotspot baru dibuat di Laravel.
     * Sync ke FreeRADIUS tables:
     * - radcheck: username + password
     * - radreply: rate-limit, session-timeout, dll
     * - radusergroup: groupname (nama paket)
     *
     * @param HotspotUser $user
     * @return void
     */
    public function created(HotspotUser $user): void
    {
        // Jangan sync jika user dibuat dengan status disabled
        if ($user->status === 'nonaktif') {
            Log::info("RADIUS OBSERVER: Hotspot user {$user->username} created as disabled, skipping sync");
            return;
        }

        try {
            $this->radiusSync->syncHotspotUser($user);
            Log::info("RADIUS OBSERVER: Hotspot user {$user->username} created and synced to RADIUS");
        } catch (\Exception $e) {
            Log::error("RADIUS OBSERVER: Failed to sync hotspot user {$user->username} on create: {$e->getMessage()}");
            // Jangan throw exception — biarkan user tetap tersimpan di Laravel
        }
    }

    /**
     * Handle the HotspotUser "updated" event.
     *
     * Dipanggil saat user hotspot diubah.
     * Kondisi:
     * - Status berubah ke DISABLED → hapus dari RADIUS
     * - Password berubah → update radcheck
     * - Profile berubah → update radreply + radusergroup
     * - Aktif → sync ulang
     *
     * @param HotspotUser $user
     * @return void
     */
    public function updated(HotspotUser $user): void
    {
        // Cek perubahan yang signifikan
        $changedFields = $user->getChanges();

        // Jika status berubah ke disabled, hapus dari RADIUS
        if (isset($changedFields['status']) && $user->status === 'nonaktif') {
            try {
                $this->radiusSync->removeUser($user->username);
                Log::info("RADIUS OBSERVER: Hotspot user {$user->username} disabled, removed from RADIUS");
            } catch (\Exception $e) {
                Log::error("RADIUS OBSERVER: Failed to remove disabled user {$user->username}: {$e->getMessage()}");
            }
            return;
        }

        // Jika user aktif, sync ulang (handle password change, profile change, dll)
        if ($user->status !== 'nonaktif') {
            try {
                $this->radiusSync->syncHotspotUser($user);

                // Log perubahan spesifik
                if (isset($changedFields['password'])) {
                    Log::info("RADIUS OBSERVER: Hotspot user {$user->username} password updated");
                }
                if (isset($changedFields['profile_id'])) {
                    Log::info("RADIUS OBSERVER: Hotspot user {$user->username} profile changed");
                }
            } catch (\Exception $e) {
                Log::error("RADIUS OBSERVER: Failed to sync hotspot user {$user->username} on update: {$e->getMessage()}");
            }
        }
    }

    /**
     * Handle the HotspotUser "deleted" event.
     *
     * Dipanggil saat user hotspot dihapus dari database.
     * Hapus dari semua tabel RADIUS:
     * - radcheck
     * - radreply
     * - radusergroup
     *
     * @param HotspotUser $user
     * @return void
     */
    public function deleted(HotspotUser $user): void
    {
        try {
            $this->radiusSync->removeUser($user->username);
            Log::info("RADIUS OBSERVER: Hotspot user {$user->username} deleted from RADIUS");
        } catch (\Exception $e) {
            Log::error("RADIUS OBSERVER: Failed to delete hotspot user {$user->username} from RADIUS: {$e->getMessage()}");
        }
    }

    /**
     * Handle the HotspotUser "force deleted" event.
     *
     * @param HotspotUser $user
     * @return void
     */
    public function forceDeleted(HotspotUser $user): void
    {
        $this->deleted($user);
    }

    /**
     * Handle the HotspotUser "restored" event (soft delete restore).
     *
     * Jika user direstore, sync ulang ke RADIUS.
     *
     * @param HotspotUser $user
     * @return void
     */
    public function restored(HotspotUser $user): void
    {
        if ($user->status !== 'nonaktif') {
            try {
                $this->radiusSync->syncHotspotUser($user);
                Log::info("RADIUS OBSERVER: Hotspot user {$user->username} restored and synced to RADIUS");
            } catch (\Exception $e) {
                Log::error("RADIUS OBSERVER: Failed to sync restored hotspot user {$user->username}: {$e->getMessage()}");
            }
        }
    }
}