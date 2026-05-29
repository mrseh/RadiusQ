<?php

namespace App\Observers;

use App\Models\Nas;
use App\Models\RadNas;
use App\Services\RadiusSyncService;
use Illuminate\Support\Facades\Log;

/**
 * NasObserver
 *
 * Observer untuk Nas model (Mikrotik/NAS devices).
 * Otomatis sync ke FreeRADIUS radnas table saat NAS dibuat/diubah/dihapus.
 *
 * Events:
 * - created: Sync new NAS ke radnas
 * - updated: Update NAS di radnas jika ada perubahan signifikan
 * - deleted: Hapus NAS dari radnas
 *
 * Sync trigger fields:
 * - ip_router: IP address berubah → update di radnas
 * - name: Nama berubah → update di radnas
 * - api_password: Secret berubah → update di radnas
 * - snmp: Community berubah → update di radnas
 *
 * Usage:
 * Daftarkan di AppServiceProvider:
 *   Nas::observe(NasObserver::class);
 */
class NasObserver
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
     * Handle the Nas "created" event.
     *
     * Dipanggil saat NAS/Mikrotik baru didaftarkan di Laravel.
     * Sync ke FreeRADIUS radnas table:
     * - nasname: IP Router
     * - shortname: Nama NAS
     * - type: mikrotik / other
     * - ports: RADIUS ports
     * - secret: Shared secret (dari api_password)
     * - community: SNMP community
     * - description: Deskripsi
     *
     * @param Nas $nas
     * @return void
     */
    public function created(Nas $nas): void
    {
        // Jangan sync jika tidak ada IP router
        if (empty($nas->ip_router)) {
            Log::warning("RADIUS OBSERVER: NAS {$nas->name} created without IP, skipping sync to radnas");
            return;
        }

        try {
            RadNas::syncFromNas($nas);
            Log::info("RADIUS OBSERVER: NAS {$nas->name} ({$nas->ip_router}) created and synced to radnas");
        } catch (\Exception $e) {
            Log::error("RADIUS OBSERVER: Failed to sync new NAS {$nas->name}: {$e->getMessage()}");
        }
    }

    /**
     * Handle the Nas "updated" event.
     *
     * Dipanggil saat NAS/Mikrotik diubah.
     * Hanya sync ulang jika ada perubahan signifikan:
     * - ip_router: IP berubah → update radnas
     * - name: Nama berubah → update radnas
     * - api_password: Secret berubah → update radnas
     * - snmp: Community berubah → update radnas
     * - type: Tipe berubah → update radnas
     *
     * @param Nas $nas
     * @return void
     */
    public function updated(Nas $nas): void
    {
        $changedFields = $nas->getChanges();

        // Daftar field yang trigger re-sync
        $syncFields = ['ip_router', 'name', 'api_password', 'snmp', 'type', 'radsec_acct_port'];

        $needsSync = false;
        foreach ($syncFields as $field) {
            if (isset($changedFields[$field])) {
                $needsSync = true;
                break;
            }
        }

        if (!$needsSync) {
            // Tidak ada perubahan signifikan
            return;
        }

        try {
            // Handle IP router change: hapus old entry
            if (isset($changedFields['ip_router']) && $changedFields['ip_router'] !== $nas->ip_router) {
                $oldIp = $changedFields['ip_router'];
                RadNas::where('nasname', $oldIp)->delete();
                Log::info("RADIUS OBSERVER: Removed old NAS entry for IP {$oldIp}");
            }

            // Sync updated NAS
            RadNas::syncFromNas($nas);
            Log::info("RADIUS OBSERVER: NAS {$nas->name} updated and synced to radnas");

            // Log perubahan spesifik
            if (isset($changedFields['ip_router'])) {
                Log::info("RADIUS OBSERVER: NAS IP changed from {$changedFields['ip_router']} to {$nas->ip_router}");
            }
            if (isset($changedFields['api_password'])) {
                Log::info("RADIUS OBSERVER: NAS {$nas->name} RADIUS secret updated");
            }

            // Trigger resync semua user di NAS ini
            if (isset($changedFields['ip_router']) || isset($changedFields['type'])) {
                $this->triggerUserResync($nas);
            }
        } catch (\Exception $e) {
            Log::error("RADIUS OBSERVER: Failed to update NAS {$nas->name}: {$e->getMessage()}");
        }
    }

    /**
     * Handle the Nas "deleted" event.
     *
     * Dipanggil saat NAS/Mikrotik dihapus dari database.
     * Hapus dari radnas table.
     *
     * @param Nas $nas
     * @return void
     */
    public function deleted(Nas $nas): void
    {
        try {
            RadNas::where('nasname', $nas->ip_router)->delete();
            Log::info("RADIUS OBSERVER: NAS {$nas->name} ({$nas->ip_router}) deleted from radnas");
        } catch (\Exception $e) {
            Log::error("RADIUS OBSERVER: Failed to delete NAS {$nas->name} from radnas: {$e->getMessage()}");
        }
    }

    /**
     * Handle the Nas "force deleted" event.
     *
     * @param Nas $nas
     * @return void
     */
    public function forceDeleted(Nas $nas): void
    {
        $this->deleted($nas);
    }

    /**
     * Handle the Nas "restored" event (soft delete restore).
     *
     * @param Nas $nas
     * @return void
     */
    public function restored(Nas $nas): void
    {
        if (!empty($nas->ip_router)) {
            try {
                RadNas::syncFromNas($nas);
                Log::info("RADIUS OBSERVER: NAS {$nas->name} restored and synced to radnas");
            } catch (\Exception $e) {
                Log::error("RADIUS OBSERVER: Failed to sync restored NAS {$nas->name}: {$e->getMessage()}");
            }
        }
    }

    /**
     * Trigger resync semua user di NAS tertentu
     *
     * Dipanggil saat IP router atau type NAS berubah.
     * Ini penting karena user mungkin perlu di-sync ulang dengan konfigurasi baru.
     *
     * @param Nas $nas
     * @return void
     */
    private function triggerUserResync(Nas $nas): void
    {
        try {
            // Catat: resync user akan dipanggil dari controller/cron
            // Observer tidak melakukan resync langsung untuk avoid infinite loop
            Log::info("RADIUS OBSERVER: User resync triggered for NAS {$nas->name} - will be processed by background job");
        } catch (\Exception $e) {
            Log::error("RADIUS OBSERVER: Failed to trigger user resync for NAS {$nas->name}: {$e->getMessage()}");
        }
    }
}