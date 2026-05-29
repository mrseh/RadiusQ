<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;

// Models
use App\Models\HotspotUser;
use App\Models\PPPoEUser;
use App\Models\HotspotProfile;
use App\Models\Nas;

// Observers
use App\Observers\HotspotUserObserver;
use App\Observers\PPPoEUserObserver;
use App\Observers\NasObserver;
use App\Observers\HotspotProfileObserver;

// Services
use App\Services\RadiusSyncService;
use App\Services\RadiusSessionService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // =============================================================
        // SINGLETON: RadiusSyncService
        // =============================================================
        // Singleton — satu instance untuk seluruh aplikasi
        // Dipakai oleh semua observers untuk sync ke FreeRADIUS
        $this->app->singleton(RadiusSyncService::class, function ($app) {
            return new RadiusSyncService();
        });

        // Alias untuk kemudahan akses
        $this->app->alias(RadiusSyncService::class, 'RadiusSync');

        // =============================================================
        // SINGLETON: RadiusSessionService
        // =============================================================
        // Singleton — satu instance untuk sync sessions
        // Dipakai oleh cron command radius:sync-sessions
        $this->app->singleton(RadiusSessionService::class, function ($app) {
            return new RadiusSessionService();
        });

        $this->app->alias(RadiusSessionService::class, 'RadiusSession');

        // =============================================================
        // BINDING: Radius Services (Prototype)
        // =============================================================
        // Prototype — instance baru setiap kali di-resolve
        // Gunakan ini jika butuh instance baru per request
        $this->app->bind('RadiusServices', function ($app) {
            return [
                'sync'    => $app->make(RadiusSyncService::class),
                'session' => $app->make(RadiusSessionService::class),
            ];
        });
    }

    /**
     * Bootstrap any application services.
     *
     * Daftarkan semua observers di sini.
     * Observers akan otomatis ter-trigger saat model dibuat/diubah/dihapus.
     */
    public function boot(): void
    {
        // =============================================================
        // MODEL OBSERVERS — FreeRADIUS Auto-Sync
        // =============================================================

        /**
         * HotspotUserObserver
         *
         * Trigger: User hotspot dibuat/diubah/dihapus
         * Actions:
         * - created  → syncHotspotUser() → radcheck + radreply + radusergroup
         * - updated  → syncHotspotUser() atau removeUser()
         * - deleted  → removeUser()
         * - restored → syncHotspotUser()
         */
        HotspotUser::observe(HotspotUserObserver::class);

        /**
         * PPPoEUserObserver
         *
         * Trigger: User PPPoE dibuat/diubah/dihapus
         * Actions:
         * - created  → syncPPPoEUser() → radcheck + radreply + radusergroup
         * - updated  → syncPPPoEUser() atau removeUser()
         * - deleted  → removeUser()
         * - restored → syncPPPoEUser()
         */
        PPPoEUser::observe(PPPoEUserObserver::class);

        /**
         * NasObserver
         *
         * Trigger: NAS/Mikrotik dibuat/diubah/dihapus
         * Actions:
         * - created  → syncFromNas() → radnas
         * - updated  → syncFromNas() jika IP/name/password berubah
         * - deleted  → removeNas()
         * - restored → syncFromNas()
         */
        Nas::observe(NasObserver::class);

        /**
         * HotspotProfileObserver
         *
         * Trigger: Paket hotspot dibuat/diubah/dihapus
         * Actions:
         * - created  → syncHotspotProfile() → radgroupreply
         * - updated  → syncHotspotProfile() jika konfigurasi berubah
         * - deleted  → removeGroup()
         * - restored → syncHotspotProfile()
         */
        HotspotProfile::observe(HotspotProfileObserver::class);

        // =============================================================
        // LOGGING — Debug observer events (development only)
        // =============================================================

        if ($this->app->environment('local', 'development')) {
            $this->enableObserverLogging();
        }

        // =============================================================
        // INITIAL SYNC — Sync existing data saat app boot
        // =============================================================

        // Uncomment baris di bawah untuk auto-sync semua data saat app starts
        // WARNING: Ini bisa lambat jika ada banyak data
        // $this->initialDataSync();
    }

    /**
     * Enable observer event logging (development only)
     *
     * Log setiap observer event untuk debugging.
     * Matikan di production untuk performance.
     */
    private function enableObserverLogging(): void
    {
        // HotspotUser events
        HotspotUser::creating(function ($user) {
            Log::debug('OBSERVER: HotspotUser creating', [
                'username' => $user->username,
                'status' => $user->status,
                'profile_id' => $user->profile_id,
            ]);
        });

        HotspotUser::created(function ($user) {
            Log::debug('OBSERVER: HotspotUser created', [
                'username' => $user->username,
            ]);
        });

        // PPPoEUser events
        PPPoEUser::creating(function ($user) {
            Log::debug('OBSERVER: PPPoEUser creating', [
                'username' => $user->username,
                'status' => $user->status,
                'package' => $user->package,
            ]);
        });

        PPPoEUser::created(function ($user) {
            Log::debug('OBSERVER: PPPoEUser created', [
                'username' => $user->username,
            ]);
        });

        // Nas events
        Nas::creating(function ($nas) {
            Log::debug('OBSERVER: Nas creating', [
                'name' => $nas->name,
                'ip_router' => $nas->ip_router,
            ]);
        });

        Nas::created(function ($nas) {
            Log::debug('OBSERVER: Nas created', [
                'name' => $nas->name,
                'ip_router' => $nas->ip_router,
            ]);
        });

        // HotspotProfile events
        HotspotProfile::creating(function ($profile) {
            Log::debug('OBSERVER: HotspotProfile creating', [
                'name' => $profile->name,
                'status' => $profile->status,
            ]);
        });

        HotspotProfile::created(function ($profile) {
            Log::debug('OBSERVER: HotspotProfile created', [
                'name' => $profile->name,
            ]);
        });
    }

    /**
     * Initial data sync saat aplikasi starts
     *
     * WARNING: Ini akan sync semua data yang ada saat ini.
     * Matikan jika ada banyak data (akan lambat).
     *
     * Alternatif: Jalankan manual via Artisan command:
     *   php artisan radius:sync-all
     */
    private function initialDataSync(): void
    {
        // Cek apakah sudah pernah di-sync sebelumnya
        // Ini bisa dicek via cache/DB flag

        if ($this->app->environment('local', 'development')) {
            // Skip di development untuk avoid duplicate sync
            return;
        }

        try {
            $radiusSync = $this->app->make(RadiusSyncService::class);

            // Sync hanya jika diperlukan
            // $radiusSync->syncAll();

            Log::info('AppServiceProvider: Initial data sync completed');
        } catch (\Exception $e) {
            Log::error('AppServiceProvider: Initial data sync failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
