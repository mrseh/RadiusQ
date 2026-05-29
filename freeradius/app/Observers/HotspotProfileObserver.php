<?php

namespace App\Observers;

use App\Models\HotspotProfile;
use App\Models\RadGroupReply;
use Illuminate\Support\Facades\Log;

/**
 * HotspotProfileObserver
 *
 * Observer untuk HotspotProfile model.
 * Otomatis sync ke FreeRADIUS radgroupreply saat paket dibuat/diubah/dihapus.
 *
 * Events:
 * - created: Sync new profile ke radgroupreply
 * - updated: Update/remove profile attributes sesuai perubahan
 * - deleted: Hapus profile dari radgroupreply
 *
 * Sync trigger fields:
 * - name: Nama paket berubah → update radgroupreply groupname
 * - rate_limit: Speed berubah → update Mikrotik-Queue-Max-Rate
 * - valid_for: Durasi berubah → update Session-Timeout, Max-All-Session
 * - shared_users: Max user berubah → update Simultaneous-Use
 * - status: Aktif/nonaktif berubah → add/remove attributes
 *
 * Usage:
 * Daftarkan di AppServiceProvider:
 *   HotspotProfile::observe(HotspotProfileObserver::class);
 */
class HotspotProfileObserver
{
    /**
     * Handle the HotspotProfile "created" event.
     *
     * Dipanggil saat paket hotspot baru dibuat di Laravel.
     * Sync konfigurasi paket ke FreeRADIUS radgroupreply:
     * - Mikrotik-Queue-Max-Rate: Batas kecepatan
     * - Mikrotik-Queue-Limit: Queue limit
     * - Session-Timeout: Maksimum waktu (dalam detik)
     * - Idle-Timeout: Maksimum idle time
     * - Max-All-Session: Total waktu maximum
     * - Reply-Message: Pesan welcome
     *
     * @param HotspotProfile $profile
     * @return void
     */
    public function created(HotspotProfile $profile): void
    {
        // Jangan sync jika profile nonaktif
        if ($profile->status !== 'active') {
            Log::info("RADIUS OBSERVER: Hotspot profile {$profile->name} created as inactive, skipping sync");
            return;
        }

        try {
            RadGroupReply::syncHotspotProfile($profile);
            Log::info("RADIUS OBSERVER: Hotspot profile {$profile->name} created and synced to radgroupreply");
        } catch (\Exception $e) {
            Log::error("RADIUS OBSERVER: Failed to sync new hotspot profile {$profile->name}: {$e->getMessage()}");
        }
    }

    /**
     * Handle the HotspotProfile "updated" event.
     *
     * Dipanggil saat paket hotspot diubah.
     * Kondisi:
     * - Status berubah ke nonaktif → hapus dari radgroupreply
     * - Konfigurasi berubah (rate_limit, valid_for, dll) → update radgroupreply
     * - Aktif → sync ulang
     *
     * @param HotspotProfile $profile
     * @return void
     */
    public function updated(HotspotProfile $profile): void
    {
        $changedFields = $profile->getChanges();

        // Jika status berubah ke nonaktif, hapus dari radgroupreply
        if (isset($changedFields['status']) && $profile->status !== 'active') {
            try {
                RadGroupReply::removeGroup($profile->name);
                Log::info("RADIUS OBSERVER: Hotspot profile {$profile->name} deactivated, removed from radgroupreply");
            } catch (\Exception $e) {
                Log::error("RADIUS OBSERVER: Failed to remove deactivated hotspot profile {$profile->name}: {$e->getMessage()}");
            }
            return;
        }

        // Jika profile aktif, sync ulang
        if ($profile->status === 'active') {
            try {
                RadGroupReply::syncHotspotProfile($profile);

                // Log perubahan spesifik
                if (isset($changedFields['rate_limit'])) {
                    Log::info("RADIUS OBSERVER: Hotspot profile {$profile->name} rate limit changed to {$profile->rate_limit}");
                }
                if (isset($changedFields['valid_for'])) {
                    Log::info("RADIUS OBSERVER: Hotspot profile {$profile->name} validity changed to {$profile->valid_for} minutes");
                }
                if (isset($changedFields['shared_users'])) {
                    Log::info("RADIUS OBSERVER: Hotspot profile {$profile->name} shared users changed to {$profile->shared_users}");
                }
                if (isset($changedFields['name'])) {
                    // Nama berubah → hapus old group, create new group
                    $oldName = $changedFields['name'];
                    RadGroupReply::where('groupname', $oldName)->delete();
                    RadGroupReply::syncHotspotProfile($profile);
                    Log::info("RADIUS OBSERVER: Hotspot profile renamed from {$oldName} to {$profile->name}");
                }
            } catch (\Exception $e) {
                Log::error("RADIUS OBSERVER: Failed to sync updated hotspot profile {$profile->name}: {$e->getMessage()}");
            }
        }
    }

    /**
     * Handle the HotspotProfile "deleted" event.
     *
     * Dipanggil saat paket hotspot dihapus dari database.
     * Hapus semua radgroupreply entries untuk group ini.
     *
     * @param HotspotProfile $profile
     * @return void
     */
    public function deleted(HotspotProfile $profile): void
    {
        try {
            RadGroupReply::where('groupname', $profile->name)->delete();
            Log::info("RADIUS OBSERVER: Hotspot profile {$profile->name} deleted from radgroupreply");
        } catch (\Exception $e) {
            Log::error("RADIUS OBSERVER: Failed to delete hotspot profile {$profile->name} from radgroupreply: {$e->getMessage()}");
        }
    }

    /**
     * Handle the HotspotProfile "force deleted" event.
     *
     * @param HotspotProfile $profile
     * @return void
     */
    public function forceDeleted(HotspotProfile $profile): void
    {
        $this->deleted($profile);
    }

    /**
     * Handle the HotspotProfile "restored" event (soft delete restore).
     *
     * @param HotspotProfile $profile
     * @return void
     */
    public function restored(HotspotProfile $profile): void
    {
        if ($profile->status === 'active') {
            try {
                RadGroupReply::syncHotspotProfile($profile);
                Log::info("RADIUS OBSERVER: Hotspot profile {$profile->name} restored and synced to radgroupreply");
            } catch (\Exception $e) {
                Log::error("RADIUS OBSERVER: Failed to sync restored hotspot profile {$profile->name}: {$e->getMessage()}");
            }
        }
    }
}