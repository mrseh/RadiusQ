<?php

namespace App\Observers;

use App\Models\PPPoEUser;
use App\Services\RadiusSyncService;
use Illuminate\Support\Facades\Log;

/**
 * PPPoEUserObserver
 *
 * Observer untuk PPPoEUser model.
 * Otomatis sync ke FreeRADIUS tables saat user dibuat/diubah/dihapus.
 *
 * Events:
 * - created: Sync new user ke radcheck, radreply, radusergroup
 * - updated: Update/remove user sesuai perubahan
 * - deleted: Hapus user dari semua tabel RADIUS
 *
 * Usage:
 * Daftarkan di AppServiceProvider:
 *   PPPoEUser::observe(PPPoEUserObserver::class);
 */
class PPPoEUserObserver
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
     * Handle the PPPoEUser "created" event.
     *
     * Dipanggil saat user PPPoE baru dibuat di Laravel.
     * Sync ke FreeRADIUS tables:
     * - radcheck: username + password + simultaneous-use
     * - radreply: rate-limit, static-ip, framed-protocol, dll
     * - radusergroup: groupname (nama paket/grup PPPoE)
     *
     * @param PPPoEUser $user
     * @return void
     */
    public function created(PPPoEUser $user): void
    {
        // Jangan sync jika user dibuat dengan status inactive
        if ($user->status === PPPoEUser::STATUS_INACTIVE) {
            Log::info("RADIUS OBSERVER: PPPoE user {$user->username} created as inactive, skipping sync");
            return;
        }

        try {
            $this->radiusSync->syncPPPoEUser($user);
            Log::info("RADIUS OBSERVER: PPPoE user {$user->username} created and synced to RADIUS");
        } catch (\Exception $e) {
            Log::error("RADIUS OBSERVER: Failed to sync PPPoE user {$user->username} on create: {$e->getMessage()}");
            // Jangan throw exception — biarkan user tetap tersimpan di Laravel
        }
    }

    /**
     * Handle the PPPoEUser "updated" event.
     *
     * Dipanggil saat user PPPoE diubah.
     * Kondisi:
     * - Status berubah ke INACTIVE → hapus dari RADIUS
     * - Password berubah → update radcheck
     * - Package berubah → update radreply + radusergroup
     * - IP address berubah → update radreply
     * - Aktif → sync ulang
     *
     * @param PPPoEUser $user
     * @return void
     */
    public function updated(PPPoEUser $user): void
    {
        // Cek perubahan yang signifikan
        $changedFields = $user->getChanges();

        // Jika status berubah ke inactive, hapus dari RADIUS
        if (isset($changedFields['status']) && $user->status === PPPoEUser::STATUS_INACTIVE) {
            try {
                $this->radiusSync->removeUser($user->username);
                Log::info("RADIUS OBSERVER: PPPoE user {$user->username} inactivated, removed from RADIUS");
            } catch (\Exception $e) {
                Log::error("RADIUS OBSERVER: Failed to remove inactive PPPoE user {$user->username}: {$e->getMessage()}");
            }
            return;
        }

        // Jika user aktif, sync ulang
        if ($user->status !== PPPoEUser::STATUS_INACTIVE) {
            try {
                $this->radiusSync->syncPPPoEUser($user);

                // Log perubahan spesifik
                if (isset($changedFields['password'])) {
                    Log::info("RADIUS OBSERVER: PPPoE user {$user->username} password updated");
                }
                if (isset($changedFields['package'])) {
                    Log::info("RADIUS OBSERVER: PPPoE user {$user->username} package changed");
                }
                if (isset($changedFields['ip_address'])) {
                    Log::info("RADIUS OBSERVER: PPPoE user {$user->username} IP address changed to {$user->ip_address}");
                }
                if (isset($changedFields['mac_address'])) {
                    Log::info("RADIUS OBSERVER: PPPoE user {$user->username} MAC address changed");
                }
            } catch (\Exception $e) {
                Log::error("RADIUS OBSERVER: Failed to sync PPPoE user {$user->username} on update: {$e->getMessage()}");
            }
        }
    }

    /**
     * Handle the PPPoEUser "deleted" event.
     *
     * Dipanggil saat user PPPoE dihapus dari database.
     * Hapus dari semua tabel RADIUS:
     * - radcheck
     * - radreply
     * - radusergroup
     *
     * @param PPPoEUser $user
     * @return void
     */
    public function deleted(PPPoEUser $user): void
    {
        try {
            $this->radiusSync->removeUser($user->username);
            Log::info("RADIUS OBSERVER: PPPoE user {$user->username} deleted from RADIUS");
        } catch (\Exception $e) {
            Log::error("RADIUS OBSERVER: Failed to delete PPPoE user {$user->username} from RADIUS: {$e->getMessage()}");
        }
    }

    /**
     * Handle the PPPoEUser "force deleted" event.
     *
     * @param PPPoEUser $user
     * @return void
     */
    public function forceDeleted(PPPoEUser $user): void
    {
        $this->deleted($user);
    }

    /**
     * Handle the PPPoEUser "restored" event (soft delete restore).
     *
     * Jika user direstore, sync ulang ke RADIUS.
     *
     * @param PPPoEUser $user
     * @return void
     */
    public function restored(PPPoEUser $user): void
    {
        if ($user->status !== PPPoEUser::STATUS_INACTIVE) {
            try {
                $this->radiusSync->syncPPPoEUser($user);
                Log::info("RADIUS OBSERVER: PPPoE user {$user->username} restored and synced to RADIUS");
            } catch (\Exception $e) {
                Log::error("RADIUS OBSERVER: Failed to sync restored PPPoE user {$user->username}: {$e->getMessage()}");
            }
        }
    }
}