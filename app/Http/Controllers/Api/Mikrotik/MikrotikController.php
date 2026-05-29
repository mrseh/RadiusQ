<?php

namespace App\Http\Controllers\Api\Mikrotik;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Mikrotik\ExecuteMikrotikScriptRequest;
use App\Http\Requests\Mikrotik\StoreMikrotikRequest;
use App\Http\Requests\Mikrotik\UpdateMikrotikRequest;
use App\Models\Mikrotik;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MikrotikController extends BaseApiController
{
    /**
     * GET /mikrotik/ajax
     */
    public function ajax(Request $request): JsonResponse
    {
        $query = Mikrotik::query();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('tipe_koneksi')) {
            $query->where('tipe_koneksi', $request->input('tipe_koneksi'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        $length = (int) $request->input('length', 25);
        $start = (int) $request->input('start', 0);
        $page = ($start / $length) + 1;

        $paginator = $query->orderByDesc('id')->paginate($length, ['*'], 'page', $page);

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => Mikrotik::count(),
            'recordsFiltered' => $paginator->total(),
            'data' => $paginator->items(),
        ]);
    }

    /**
     * POST /mikrotik/store
     */
    public function store(StoreMikrotikRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $data['status'] = $data['status'] ?? 'offline';

            $mikrotik = Mikrotik::create($data);

            return $this->ok([
                'id' => $mikrotik->id,
                'nama' => $mikrotik->nama,
                'ip_address' => $mikrotik->ip_address,
            ], 'Mikrotik berhasil ditambahkan');
        } catch (\Exception $e) {
            Log::error('Mikrotik store failed: ' . $e->getMessage());

            return $this->error('Gagal menambah mikrotik', 500);
        }
    }

    /**
     * PUT /mikrotik/update/:id
     */
    public function update(UpdateMikrotikRequest $request, int $id): JsonResponse
    {
        try {
            $mikrotik = Mikrotik::find($id);

            if (!$mikrotik) {
                return $this->error('Mikrotik tidak ditemukan', 404);
            }

            $mikrotik->update($request->validated());

            return $this->ok([
                'id' => $mikrotik->id,
                'nama' => $mikrotik->nama,
            ], 'Mikrotik berhasil diperbarui');
        } catch (\Exception $e) {
            Log::error('Mikrotik update failed: ' . $e->getMessage());

            return $this->error('Gagal memperbarui mikrotik', 500);
        }
    }

    /**
     * GET /mikrotik/show/:id
     * Returns Mikrotik details including active sessions count.
     */
    public function show(int $id): JsonResponse
    {
        $mikrotik = Mikrotik::find($id);

        if (!$mikrotik) {
            return $this->error('Mikrotik tidak ditemukan', 404);
        }

        $activeSessions = $this->countActiveSessions($mikrotik);

        return $this->ok([
            'id' => $mikrotik->id,
            'nama' => $mikrotik->nama,
            'ip_address' => $mikrotik->ip_address,
            'tipe_koneksi' => $mikrotik->tipe_koneksi,
            'snmp_status' => $mikrotik->snmp_status,
            'script' => $mikrotik->script,
            'status' => $mikrotik->status,
            'active_sessions' => $activeSessions,
        ]);
    }

    /**
     * DELETE /mikrotik/destroy/:id
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $mikrotik = Mikrotik::find($id);

            if (!$mikrotik) {
                return $this->error('Mikrotik tidak ditemukan', 404);
            }

            $mikrotik->delete();

            return $this->ok(null, 'Mikrotik berhasil dihapus');
        } catch (\Exception $e) {
            Log::error('Mikrotik destroy failed: ' . $e->getMessage());

            return $this->error('Gagal menghapus mikrotik', 500);
        }
    }

    /**
     * POST /mikrotik/probe/:id
     * Probe/ping the Mikrotik to check connectivity.
     * Tries RouterOS API first, falls back to socket_connect and exec ping.
     */
    public function probe(int $id): JsonResponse
    {
        $mikrotik = Mikrotik::find($id);

        if (!$mikrotik) {
            return $this->error('Mikrotik tidak ditemukan', 404);
        }

        $result = $this->probeMikrotik($mikrotik);

        return response()->json([
            'status' => true,
            'message' => $result['online'] ? 'Mikrotik online' : 'Mikrotik offline',
            'data' => [
                'id' => $mikrotik->id,
                'online' => $result['online'],
                'latency_ms' => $result['latency_ms'],
                'method' => $result['method'],
            ],
        ]);
    }

    /**
     * POST /mikrotik/:id/script
     * Execute a script on the Mikrotik via RouterOS API or fallback.
     */
    public function script(ExecuteMikrotikScriptRequest $request, int $id): JsonResponse
    {
        $mikrotik = Mikrotik::find($id);

        if (!$mikrotik) {
            return $this->error('Mikrotik tidak ditemukan', 404);
        }

        $script = $request->validated('script');

        try {
            if ($this->routerosApiAvailable()) {
                $output = $this->executeScriptViaApi($mikrotik, $script);
            } else {
                $output = $this->executeScriptViaSsh($mikrotik, $script);
            }

            return $this->ok([
                'output' => $output,
            ], 'Script berhasil dieksekusi');
        } catch (\Exception $e) {
            Log::error('Mikrotik script execution failed: ' . $e->getMessage());

            return $this->error('Gagal mengeksekusi script: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Try to ping via exec; returns ['online' => bool, 'latency_ms' => float|null, 'method' => string].
     */
    private function probeMikrotik(Mikrotik $mikrotik): array
    {
        $host = $mikrotik->ip_address;

        // Method 1: exec ping (Linux)
        if (function_exists('exec')) {
            $pingCmd = "ping -c 1 -W 2 " . escapeshellarg($host) . " 2>&1";
            $output = [];
            $latency = null;
            $exitCode = 0;
            exec($pingCmd, $output, $exitCode);

            if ($exitCode === 0 && !empty($output)) {
                foreach ($output as $line) {
                    if (preg_match('/time[=<](\d+(?:\.\d+)?)\s*ms/i', $line, $matches)) {
                        $latency = (float) $matches[1];
                        break;
                    }
                }

                return [
                    'online' => true,
                    'latency_ms' => $latency,
                    'method' => 'exec_ping',
                ];
            }
        }

        // Method 2: socket_connect to port 8728 (RouterOS API) or 80 (HTTP)
        if (function_exists('socket_create')) {
            $socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            if ($socket) {
                socket_set_nonblock($socket);
                $start = microtime(true);
                @$connected = @socket_connect($socket, $host, 8728);
                $elapsed = (microtime(true) - $start) * 1000;

                if ($connected) {
                    socket_close($socket);

                    return [
                        'online' => true,
                        'latency_ms' => round($elapsed, 2),
                        'method' => 'socket_api',
                    ];
                }

                // Try HTTP port
                socket_close($socket);
                $socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
                if ($socket) {
                    $start = microtime(true);
                    @$connected = @socket_connect($socket, $host, 80);
                    $elapsed = (microtime(true) - $start) * 1000;

                    if ($connected) {
                        socket_close($socket);

                        return [
                            'online' => true,
                            'latency_ms' => round($elapsed, 2),
                            'method' => 'socket_http',
                        ];
                    }
                    socket_close($socket);
                }
            }
        }

        return [
            'online' => false,
            'latency_ms' => null,
            'method' => 'unreachable',
        ];
    }

    /**
     * Count active sessions from Mikrotik (hotspot/pppoe).
     */
    private function countActiveSessions(Mikrotik $mikrotik): int
    {
        try {
            // Use exec approach: query active connections via Mikrotik API
            // Requires php-routeros-api or direct socket communication
            // Placeholder: return 0 until a proper API integration is set up
            return 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Check if PEAR Net_Socket or a RouterOS API package is available.
     */
    private function routerosApiAvailable(): bool
    {
        return class_exists(\PEAR::class)
            || class_exists(\RouterOS::class)
            || class_exists(\MikrotikAPI::class);
    }

    /**
     * Execute script via RouterOS API.
     * Requires php-routeros-api package or native RouterOS socket protocol.
     * Protocol spec: login, then /command with array parsed responses.
     */
    private function executeScriptViaApi(Mikrotik $mikrotik, string $script): string
    {
        // Check for php-os/routeros (PEAR-based) in include path
        $apiPort = 8728;
        $host = $mikrotik->ip_address;
        $socket = @fsockopen($host, $apiPort, $errno, $errstr, 5);

        if (!$socket) {
            throw new \Exception("Tidak dapat terhubung ke RouterOS di {$host}:{$apiPort}");
        }

        stream_set_timeout($socket, 10);

        // Send login sentence (username + challenge hashed with md5)
        $username = config('mikrotik.username', 'admin');
        $password = config('mikrotik.password', '');

        fwrite($socket, "/login\n");
        $resp = fread($socket, 4096);

        $challenge = null;
        if (preg_match('/=ret=(\S+)/', $resp, $matches)) {
            $challenge = $matches[1];
        }

        $loginResponse = '';
        if ($challenge) {
            $hash = md5("\x00" . $password . $challenge);
            $loginResponse = "/login\n=-name={$username}\n=response=00{$hash}\n";
        } else {
            $loginResponse = "/login\n=-name={$username}\n=password={$password}\n";
        }

        fwrite($socket, $loginResponse);
        $loginResp = fread($socket, 4096);

        if (!str_contains($loginResp, '=done') && !str_contains($loginResp, '=ret')) {
            fclose($socket);
            throw new \Exception('Login ke RouterOS gagal');
        }

        // Execute script
        fwrite($socket, "/system/script/run\n.source={$script}\n");
        $out = '';
        while (!feof($socket)) {
            $line = fread($socket, 4096);
            $out .= $line;
            if (str_contains($line, '=done')) {
                break;
            }
        }

        fclose($socket);

        // Strip API sentence wrappers for plain text output
        $lines = explode("\n", trim($out));
        $clean = [];
        foreach ($lines as $line) {
            if (str_starts_with($line, '!re') || str_starts_with($line, '!done')) {
                continue;
            }
            $clean[] = preg_replace('/^\d+=.*?=/', '', $line);
        }

        return implode("\n", array_filter($clean));
    }

    /**
     * Fallback: execute script by SSH or direct shell.
     * This requires ssh2 extension or a configured SSH key setup.
     */
    private function executeScriptViaSsh(Mikrotik $mikrotik, string $script): string
    {
        if (!function_exists('ssh2_connect')) {
            throw new \Exception('SSH2 extension tidak tersedia dan RouterOS API tidak terpasang');
        }

        $conn = ssh2_connect($mikrotik->ip_address, 22);
        if (!$conn) {
            throw new \Exception('Tidak dapat terhubung via SSH');
        }

        $username = config('mikrotik.username', 'admin');
        $password = config('mikrotik.password', '');

        if (!ssh2_auth_password($conn, $username, $password)) {
            throw new \Exception('SSH authentication gagal');
        }

        $stream = ssh2_exec($conn, $script);
        stream_set_blocking($stream, true);
        $output = stream_get_contents($stream);
        fclose($stream);
        ssh2_disconnect($conn);

        return $output;
    }
}
