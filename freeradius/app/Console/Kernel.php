<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * Cron schedule untuk RADIUS sync jobs.
     *
     * Schedule:
     * - Every 1 minute: sync sessions dari radacct
     * - Every 5 minutes: sync profiles + NAS
     * - Every 6 hours: full user sync
     * - Every 1 hour: cleanup expired sessions
     */
    protected function schedule(Schedule $schedule): void
    {
        // =============================================================
        // SESSION SYNC — Every 1 minute
        // =============================================================
        // Sync active sessions dari radacct ke Laravel tables
        // Ini memastikan dashboard menampilkan online users secara real-time
        //
        // */1 * * * * php /path/to/artisan radius:sync-sessions
        $schedule->command('radius:sync-sessions')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/radius-sync.log'));

        // =============================================================
        // PROFILE + NAS SYNC — Every 5 minutes
        // =============================================================
        // Sync profiles dan NAS devices ke FreeRADIUS
        // Ini memastikan konfigurasi paket terbaru tersedia di RADIUS
        //
        // */5 * * * * php /path/to/artisan radius:sync-all --profiles
        $schedule->command('radius:sync-all --profiles --nas')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/radius-sync.log'));

        // =============================================================
        // FULL USER SYNC — Every 6 hours
        // =============================================================
        // Sync semua users ke FreeRADIUS
        // Ini untuk jaga-jaga jika ada user yang miss sync dari observer
        //
        // 0 */6 * * * php /path/to/artisan radius:sync-all --users
        $schedule->command('radius:sync-all --users')
            ->hourlyAt(0)
            ->everySixHours()
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/radius-sync.log'));

        // =============================================================
        // CLEANUP — Every 1 hour
        // =============================================================
        // Cleanup orphan entries (user yang ada di RADIUS tapi tidak ada di Laravel)
        //
        // 0 * * * * php /path/to/artisan radius:sync-all --cleanup
        $schedule->command('radius:sync-all --cleanup')
            ->hourly()
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/radius-cleanup.log'));

        // =============================================================
        // MONTHLY VERIFICATION — Day 1, 00:00
        // =============================================================
        // Full sync + verification di awal bulan
        //
        // 0 0 1 * * php /path/to/artisan radius:sync-all
        $schedule->command('radius:sync-all')
            ->monthlyOn(1, '00:00')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/radius-full.log'));
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}