<?php

namespace App\Console\Commands;

use App\Services\RadiusSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * SyncRadiusAllCommand
 *
 * Artisan command untuk sync semua data dari Laravel ke FreeRADIUS tables.
 *
 * Melakukan:
 * 1. Sync semua hotspot profiles → radgroupreply
 * 2. Sync semua PPPoE profiles → radgroupreply
 * 3. Sync semua NAS devices → radnas
 * 4. Sync semua hotspot users → radcheck + radreply + radusergroup
 * 5. Sync semua PPPoE users → radcheck + radreply + radusergroup
 *
 * Usage:
 *   php artisan radius:sync-all              # Full sync semua data
 *   php artisan radius:sync-all --profiles    # Sync profiles saja
 *   php artisan radius:sync-all --users       # Sync users saja
 *   php artisan radius:sync-all --nas         # Sync NAS saja
 *   php artisan radius:sync-all --verify      # Verifikasi user saja
 *   php artisan radius:sync-all --stats       # Show stats
 *
 * Cron Schedule:
 *   */5 * * * * php /path/to/artisan radius:sync-all --profiles >> /dev/null 2>&1
 *   0 */6 * * * php /path/to/artisan radius:sync-all --users >> /dev/null 2>&1
 */
class SyncRadiusAllCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'radius:sync-all
                            {--profiles : Sync profiles only}
                            {--users : Sync users only}
                            {--nas : Sync NAS devices only}
                            {--verify : Verify existing users only}
                            {--stats : Show stats only}
                            {--cleanup : Cleanup orphan entries}';

    /**
     * The console command description.
     */
    protected $description = 'Sync semua data dari Laravel ke FreeRADIUS tables';

    /**
     * RadiusSyncService instance
     */
    private RadiusSyncService $radiusSync;

    /**
     * Constructor
     */
    public function __construct(RadiusSyncService $radiusSync)
    {
        parent::__construct();
        $this->radiusSync = $radiusSync;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('═══════════════════════════════════════════════════════');
        $this->info('  RADIUS Full Sync');
        $this->info('═══════════════════════════════════════════════════════');
        $this->newLine();

        // Stats mode
        if ($this->option('stats')) {
            return $this->showStats();
        }

        // Verify mode
        if ($this->option('verify')) {
            return $this->verifyUsers();
        }

        // Cleanup mode
        if ($this->option('cleanup')) {
            return $this->cleanupOrphans();
        }

        // Selective sync modes
        if ($this->option('profiles')) {
            return $this->syncProfiles();
        }

        if ($this->option('users')) {
            return $this->syncUsers();
        }

        if ($this->option('nas')) {
            return $this->syncNas();
        }

        // Default: full sync
        return $this->fullSync();
    }

    /**
     * Full sync semua data
     */
    private function fullSync(): int
    {
        $this->info('🔄 Starting full RADIUS sync...');
        $this->newLine();

        $startTime = microtime(true);

        try {
            $this->radiusSync->syncAll();

            $duration = round(microtime(true) - $startTime, 2);

            $this->newLine();
            $this->info('✅ Full sync completed successfully!');
            $this->info("⏱️  Duration: {$duration} seconds");
            $this->newLine();

            $this->showStats();

            Log::info('radius:sync-all completed', ['duration' => $duration]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Full sync failed: {$e->getMessage()}");
            Log::error('radius:sync-all failed', ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }
    }

    /**
     * Sync profiles only
     */
    private function syncProfiles(): int
    {
        $this->info('📦 Syncing profiles...');
        $this->newLine();

        try {
            $this->radiusSync->syncHotspotProfiles();
            $this->info('   ✅ Hotspot profiles synced');

            $this->radiusSync->syncPPPoEProfiles();
            $this->info('   ✅ PPPoE profiles synced');

            $this->newLine();
            $this->info('✅ Profile sync completed!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Profile sync failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    /**
     * Sync users only
     */
    private function syncUsers(): int
    {
        $this->info('👥 Syncing users...');
        $this->newLine();

        try {
            $this->radiusSync->syncAllHotspotUsers();
            $this->info('   ✅ Hotspot users synced');

            $this->radiusSync->syncAllPPPoEUsers();
            $this->info('   ✅ PPPoE users synced');

            $this->newLine();
            $this->info('✅ User sync completed!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ User sync failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    /**
     * Sync NAS only
     */
    private function syncNas(): int
    {
        $this->info('🖥️  Syncing NAS devices...');
        $this->newLine();

        try {
            $this->radiusSync->syncNasDevices();

            $this->newLine();
            $this->info('✅ NAS sync completed!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ NAS sync failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    /**
     * Verify existing users
     */
    private function verifyUsers(): int
    {
        $this->info('🔍 Verifying RADIUS users...');
        $this->newLine();

        try {
            $stats = $this->radiusSync->getStats();

            $this->table(
                ['Table', 'Records'],
                [
                    ['radcheck', $stats['radcheck']],
                    ['radreply', $stats['radreply']],
                    ['radusergroup', $stats['radusergroup']],
                    ['radgroupreply', $stats['radgroupreply']],
                    ['radnas', $stats['radnas']],
                ]
            );

            $this->newLine();
            $this->info('✅ Verification completed!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Verification failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    /**
     * Show stats
     */
    private function showStats(): int
    {
        try {
            $stats = $this->radiusSync->getStats();

            $this->info('📊 RADIUS Tables Stats');
            $this->info('────────────────────────────────────');
            $this->table(
                ['Table', 'Records'],
                [
                    ['radcheck', $stats['radcheck']],
                    ['radreply', $stats['radreply']],
                    ['radusergroup', $stats['radusergroup']],
                    ['radgroupreply', $stats['radgroupreply']],
                    ['radnas', $stats['radnas']],
                ]
            );

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Failed to get stats: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    /**
     * Cleanup orphan entries
     */
    private function cleanupOrphans(): int
    {
        $this->info('🧹 Cleaning up orphan entries...');
        $this->newLine();

        try {
            $count = $this->radiusSync->cleanupOrphans();

            $this->info("✅ Cleaned up {$count} orphan entries");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Cleanup failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}