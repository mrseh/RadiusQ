<?php

namespace App\Console\Commands;

use App\Services\RadiusSessionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * SyncRadiusSessionsCommand
 *
 * Artisan command untuk sync active sessions dari FreeRADIUS (radacct)
 * ke Laravel tables (hotspot_sessions, pppoe_sessions).
 *
 * Usage:
 *   php artisan radius:sync-sessions           # Sync active sessions
 *   php artisan radius:sync-sessions --stats   # Show stats only
 *   php artisan radius:sync-sessions --full     # Full sync (active + cleanup)
 *
 * Cron Schedule:
 *   * * * * * php /path/to/artisan radius:sync-sessions >> /dev/null 2>&1
 */
class SyncRadiusSessionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'radius:sync-sessions
                            {--full : Full sync (active + cleanup expired)}
                            {--stats : Show online stats only}';

    /**
     * The console command description.
     */
    protected $description = 'Sync active sessions dari FreeRADIUS radacct ke Laravel tables';

    /**
     * RadiusSessionService instance
     */
    private RadiusSessionService $sessionService;

    /**
     * Constructor
     */
    public function __construct(RadiusSessionService $sessionService)
    {
        parent::__construct();
        $this->sessionService = $sessionService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('═══════════════════════════════════════════════════════');
        $this->info('  RADIUS Session Sync');
        $this->info('═══════════════════════════════════════════════════════');
        $this->newLine();

        // Stats mode
        if ($this->option('stats')) {
            return $this->showStats();
        }

        // Full sync mode
        if ($this->option('full')) {
            return $this->fullSync();
        }

        // Default: sync active sessions
        return $this->syncActiveSessions();
    }

    /**
     * Sync active sessions only
     */
    private function syncActiveSessions(): int
    {
        $this->info('📡 Syncing active sessions from radacct...');
        $this->newLine();

        try {
            $count = $this->sessionService->syncActiveSessions();

            $this->info("✅ Successfully synced {$count} active sessions");
            $this->newLine();

            // Show quick stats
            $this->showQuickStats();

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Sync failed: {$e->getMessage()}");
            Log::error('radius:sync-sessions failed', ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }
    }

    /**
     * Full sync (active + cleanup)
     */
    private function fullSync(): int
    {
        $this->info('📡 Running full session sync (active + cleanup)...');
        $this->newLine();

        try {
            $result = $this->sessionService->fullSync();

            $this->info("✅ Active sessions synced: {$result['active_synced']}");
            $this->info("🧹 Expired sessions cleaned: {$result['expired_cleaned']}");
            $this->newLine();

            // Show quick stats
            $this->showQuickStats();

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Full sync failed: {$e->getMessage()}");
            Log::error('radius:sync-sessions --full failed', ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }
    }

    /**
     * Show stats only
     */
    private function showStats(): int
    {
        try {
            $stats = $this->sessionService->getOnlineStats();

            $this->info('📊 Online Sessions Stats');
            $this->info('────────────────────────────────');
            $this->table(
                ['Metric', 'Count'],
                [
                    ['PPPoE Online', $stats['pppoe_online']],
                    ['Hotspot Online (last 30 min)', $stats['hotspot_online']],
                    ['Active in radacct', $stats['total_radacct_active']],
                ]
            );

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Failed to get stats: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    /**
     * Show quick stats after sync
     */
    private function showQuickStats(): void
    {
        try {
            $stats = $this->sessionService->getOnlineStats();

            $this->info('📊 Quick Stats:');
            $this->table(
                ['Type', 'Online'],
                [
                    ['PPPoE', $stats['pppoe_online']],
                    ['Hotspot (30 min)', $stats['hotspot_online']],
                ]
            );
        } catch (\Exception $e) {
            // Ignore stats errors
        }
    }
}