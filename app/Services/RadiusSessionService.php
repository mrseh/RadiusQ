<?php

namespace App\Services;

use App\Models\HotspotUser;
use App\Models\PPPoEUser;
use App\Models\HotspotSession;
use App\Models\PPPoESession;
use App\Models\Nas;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RadiusSessionService
 *
 * Service untuk sync session dari FreeRADIUS (radacct) ke Laravel tables.
 *
 * Fungsi:
 * 1. Ambil active sessions dari radacct
 * 2. Sync ke hotspot_sessions dan pppoe_sessions (denormalized)
 * 3. Cleanup expired sessions
 *
 * Dipanggil oleh:
 * - Cron job: php artisan radius:sync-sessions (setiap 1 menit)
 * - Artisan command: php artisan radius:sync-sessions
 */
class RadiusSessionService
{
    /**
     * Sync active sessions dari radacct ke Laravel tables
     *
     * Ambil sessions yang:
     * - acctstoptime = NULL (still active)
     * - acctstarttime > 24 jam lalu (cleanup old records)
     *
     * @return int Jumlah sessions yang di-sync
     */
    public function syncActiveSessions(): int
    {
        $this->info('Starting active session sync...');

        // Ambil active sessions dari radacct
        // Sessions dengan acctstoptime = NULL adalah yang masih aktif
        $activeSessions = DB::table('radacct')
            ->whereNull('acctstoptime')
            ->where('acctstarttime', '>', now()->subHours(24))
            ->orderBy('acctstarttime', 'desc')
            ->get();

        $this->info("Found {$activeSessions->count()} active sessions in radacct");

        $count = 0;
        foreach ($activeSessions as $session) {
            // Cek apakah PPPoE user atau Hotspot user
            $pppoeUser = PPPoEUser::where('username', $session->username)->first();

            if ($pppoeUser) {
                $this->syncPPPoESession($session);
                $count++;
            } else {
                $hotspotUser = HotspotUser::where('username', $session->username)->first();
                if ($hotspotUser) {
                    $this->syncHotspotSession($session);
                    $count++;
                }
            }
        }

        $this->info("Synced {$count} active sessions to Laravel tables");
        return $count;
    }

    /**
     * Sync satu PPPoE session dari radacct
     *
     * @param object $session
     * @return void
     */
    private function syncPPPoESession(object $session): void
    {
        // Cari NAS ID
        $nas = Nas::where('ip_router', $session->nasipaddress)->first();

        // Calculate session time
        $sessionTime = $this->calculateSessionTime(
            $session->acctstarttime,
            $session->acctstoptime,
            $session->acctsessiontime
        );

        // Update or create session
        PPPoESession::updateOrCreate(
            [
                'username' => $session->username,
                'nas_name' => $session->nasipaddress,
            ],
            [
                'ip_address'    => $session->framedipaddress,
                'mac_address'   => $session->callingstationid,
                'nas_id'        => $nas?->id,
                'nas_name'      => $session->nasipaddress,
                'uptime'        => $this->formatUptime($sessionTime),
                'input_octets'  => $session->acctinputoctets ?? 0,
                'output_octets' => $session->acctoutputoctets ?? 0,
                'session_time'  => $sessionTime,
                'status'        => 'online',
                'start_time'    => $session->acctstarttime,
                'last_update'   => now(),
            ]
        );
    }

    /**
     * Sync satu Hotspot session dari radacct
     *
     * @param object $session
     * @return void
     */
    private function syncHotspotSession(object $session): void
    {
        // Calculate session time
        $sessionTime = $this->calculateSessionTime(
            $session->acctstarttime,
            $session->acctstoptime,
            $session->acctsessiontime
        );

        // Update or create session
        HotspotSession::updateOrCreate(
            ['username' => $session->username],
            [
                'nas'           => $session->nasipaddress,
                'ip_address'    => $session->framedipaddress,
                'mac_address'   => $session->callingstationid,
                'input_octets'  => $session->acctinputoctets ?? 0,
                'output_octets' => $session->acctoutputoctets ?? 0,
                'session_time'  => $sessionTime,
                'start_time'    => $session->acctstarttime,
                'terminate_cause' => $session->acctterminatecause,
            ]
        );
    }

    /**
     * Cleanup expired sessions
     *
     * Hapus sessions yang:
     * - acctstoptime NOT NULL (sudah selesai)
     * - acctstoptime > 24 jam lalu
     *
     * @return int Jumlah sessions yang di-archive
     */
    public function cleanupExpiredSessions(): int
    {
        $this->info('Cleaning up expired sessions...');

        // Update pppoe_sessions yang sudah selesai
        $expiredPPPoE = DB::table('radacct')
            ->whereNotNull('acctstoptime')
            ->where('acctstoptime', '<', now()->subHours(24))
            ->get();

        $count = 0;
        foreach ($expiredPPPoE as $session) {
            // Check if user is in PPPoE
            if (PPPoEUser::where('username', $session->username)->exists()) {
                PPPoESession::where('username', $session->username)
                    ->where('nas_name', $session->nasipaddress)
                    ->update([
                        'status' => 'offline',
                        'last_update' => $session->acctstoptime,
                    ]);
                $count++;
            }
        }

        // Cleanup old hotspot sessions
        HotspotSession::where('start_time', '<', now()->subDays(7))
            ->whereNotNull('terminate_cause')
            ->delete();

        $this->info("Cleaned up {$count} expired PPPoE sessions");
        return $count;
    }

    /**
     * Full session sync (active + cleanup)
     *
     * @return array
     */
    public function fullSync(): array
    {
        $activeCount = $this->syncActiveSessions();
        $cleanupCount = $this->cleanupExpiredSessions();

        return [
            'active_synced' => $activeCount,
            'expired_cleaned' => $cleanupCount,
        ];
    }

    // =============================================================
    // HELPER METHODS
    // =============================================================

    /**
     * Calculate session time in seconds
     *
     * @param string|null $startTime
     * @param string|null $stopTime
     * @param int|null $sessionTime
     * @return int
     */
    private function calculateSessionTime(?string $startTime, ?string $stopTime, ?int $sessionTime): int
    {
        // Jika ada acctsessiontime langsung dari radacct, gunakan itu
        if ($sessionTime !== null) {
            return (int) $sessionTime;
        }

        // Hitung dari start dan stop time
        if ($startTime && $stopTime) {
            $start = \Carbon\Carbon::parse($startTime);
            $stop = \Carbon\Carbon::parse($stopTime);
            return $stop->diffInSeconds($start);
        }

        // Hitung dari start time ke sekarang (still active)
        if ($startTime) {
            $start = \Carbon\Carbon::parse($startTime);
            return $start->diffInSeconds(now());
        }

        return 0;
    }

    /**
     * Format seconds to uptime string (e.g., "2d 5h 30m 15s")
     *
     * @param int $seconds
     * @return string
     */
    private function formatUptime(int $seconds): string
    {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        $parts = [];
        if ($days > 0) $parts[] = "{$days}d";
        if ($hours > 0) $parts[] = "{$hours}h";
        if ($minutes > 0) $parts[] = "{$minutes}m";
        if ($secs > 0 || empty($parts)) $parts[] = "{$secs}s";

        return implode(' ', $parts);
    }

    /**
     * Output info message
     *
     * @param string $message
     * @return void
     */
    private function info(string $message): void
    {
        Log::info("[RadiusSessionService] {$message}");
    }

    // =============================================================
    // STATS & MONITORING
    // =============================================================

    /**
     * Get online sessions stats
     *
     * @return array
     */
    public function getOnlineStats(): array
    {
        return [
            'pppoe_online' => PPPoESession::where('status', 'online')->count(),
            'hotspot_online' => HotspotSession::where('start_time', '>', now()->subMinutes(30))
                ->whereNull('terminate_cause')
                ->count(),
            'total_radacct_active' => DB::table('radacct')
                ->whereNull('acctstoptime')
                ->where('acctstarttime', '>', now()->subHours(24))
                ->count(),
        ];
    }

    /**
     * Get session history untuk user
     *
     * @param string $username
     * @param int $limit
     * @return array
     */
    public function getUserSessionHistory(string $username, int $limit = 10): array
    {
        return DB::table('radacct')
            ->where('username', $username)
            ->orderBy('acctstarttime', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}