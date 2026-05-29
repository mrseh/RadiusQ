<?php

namespace App\Http\Controllers\Api\Olt;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\Olt\BulkActionRequest;
use App\Http\Requests\Api\Olt\StoreOltRequest;
use App\Models\Olt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class OltController extends BaseApiController
{
    /**
     * GET /olt
     * Base view — redirect to ajax or return first record.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('length', 25), 100);
        $page = (int) $request->get('start', 0) > 0
            ? (int) floor($request->get('start', 0) / $perPage) + 1
            : 1;

        $query = Olt::query()
            ->when($request->get('search'), fn($q) => $q->where('nama', 'like', '%' . $request->get('search') . '%')
                ->orWhere('ip_address', 'like', '%' . $request->get('search') . '%'))
            ->when($request->get('lokasi'), fn($q) => $q->where('lokasi', $request->get('lokasi')))
            ->when($request->get('status'), fn($q) => $q->where('status', $request->get('status')))
            ->orderBy('nama');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return $this->paginate($paginator);
    }

    /**
     * GET /olt/ajax
     * DataTable list.
     */
    public function ajax(Request $request): JsonResponse
    {
        return $this->index($request);
    }

    /**
     * POST /olt
     * Create a new OLT.
     */
    public function store(StoreOltRequest $request): JsonResponse
    {
        $data = $request->validated();
        $olt = Olt::create($data);

        Log::info('OLT: Created', ['id' => $olt->id, 'nama' => $olt->nama]);

        return $this->ok($olt, 'OLT berhasil ditambahkan', 201);
    }

    /**
     * GET /olt/:id
     * Show OLT detail.
     */
    public function show(int $id): JsonResponse
    {
        $olt = Olt::find($id);

        if (!$olt) {
            return $this->error('OLT tidak ditemukan', 404);
        }

        return $this->ok($olt);
    }

    /**
     * GET /olt/status/:id
     * Probe OLT status via SNMP or ping.
     */
    public function status(int $id): JsonResponse
    {
        $olt = Olt::find($id);

        if (!$olt) {
            return $this->error('OLT tidak ditemukan', 404);
        }

        $online = $this->probeStatus($olt);

        $olt->updateQuietly(['status' => $online ? 'online' : 'offline']);

        return $this->ok([
            'id' => $olt->id,
            'nama' => $olt->nama,
            'ip_address' => $olt->ip_address,
            'status' => $olt->fresh()->status,
            'online' => $online,
        ]);
    }

    /**
     * GET /olt/:id/hioso
     * Huawei GPON OLT interface / GPON port list.
     */
    public function hioso(int $id): JsonResponse
    {
        $olt = Olt::find($id);

        if (!$olt) {
            return $this->error('OLT tidak ditemukan', 404);
        }

        $ports = $this->huaweiGetPorts($olt, 'gpon');

        return $this->ok([
            'olt' => $olt,
            'ports' => $ports,
        ]);
    }

    /**
     * GET /olt/:id/hsgq
     * Huawei OLT interface list (HSGQ / another variant).
     */
    public function hsgq(int $id): JsonResponse
    {
        $olt = Olt::find($id);

        if (!$olt) {
            return $this->error('OLT tidak ditemukan', 404);
        }

        $ports = $this->huaweiGetPorts($olt, 'hsgq');

        return $this->ok([
            'olt' => $olt,
            'ports' => $ports,
        ]);
    }

    /**
     * PUT /olt/:id
     * Update OLT.
     */
    public function update(StoreOltRequest $request, int $id): JsonResponse
    {
        $olt = Olt::find($id);

        if (!$olt) {
            return $this->error('OLT tidak ditemukan', 404);
        }

        $olt->update($request->validated());

        Log::info('OLT: Updated', ['id' => $olt->id]);

        return $this->ok($olt->fresh(), 'OLT berhasil diperbarui');
    }

    /**
     * DELETE /olt/:id
     * Delete OLT.
     */
    public function destroy(int $id): JsonResponse
    {
        $olt = Olt::find($id);

        if (!$olt) {
            return $this->error('OLT tidak ditemukan', 404);
        }

        $olt->delete();

        Log::info('OLT: Deleted', ['id' => $id]);

        return $this->ok(null, 'OLT berhasil dihapus');
    }

    /**
     * Update OLT status via SNMP or ping fallback.
     */
    protected function probeStatus(Olt $olt): bool
    {
        $ip = $olt->ip_address;
        $port = $olt->snmp_port ?? 161;

        // Try SNMP walk first if snmpwalk is available
        if ($this->snmpAvailable()) {
            $community = $olt->snmp_community ?? 'public';
            $output = @exec("snmpwalk -v 2c -c {$community} {$ip}:{$port} system.sysUpTime.0 2>&1");

            if ($output && !str_contains($output, 'Timeout') && !str_contains($output, 'No Response')) {
                return true;
            }
        }

        // Fallback: TCP ping via fsockopen
        return $this->tcpPing($ip, $port);
    }

    /**
     * Check if snmpwalk binary is available.
     */
    protected function snmpAvailable(): bool
    {
        return file_exists('/usr/bin/snmpwalk') || file_exists('/usr/local/bin/snmpwalk');
    }

    /**
     * TCP ping using fsockopen.
     */
    protected function tcpPing(string $host, int $port, int $timeout = 3): bool
    {
        $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);

        if ($fp) {
            fclose($fp);
            return true;
        }

        return false;
    }

    /**
     * Fetch Huawei OLT port data via SNMP or return mocked data.
     */
    protected function huaweiGetPorts(Olt $olt, string $variant = 'gpon'): array
    {
        $ip = $olt->ip_address;
        $snmpPort = $olt->snmp_port ?? 161;
        $community = $olt->snmp_community ?? 'public';

        if ($this->snmpAvailable()) {
            // Attempt snmpwalk on Huawei GPON OLT interface table
            if ($variant === 'gpon') {
                $oid = '1.3.6.1.4.1.2011.6.128.1.1.2.43.1.4'; // hwGponDevicePortTable
            } else {
                $oid = '1.3.6.1.4.1.2011.6.128.1.1.2.41.1.4'; // hwGponInterfaceTable (HSGQ variant)
            }

            $output = @exec("snmpwalk -v 2c -c {$community} {$ip}:{$snmpPort} {$oid} 2>&1");

            if ($output && !str_contains($output, 'Timeout') && !str_contains($output, 'No Response')) {
                return $this->parseSnmpPorts($output, $variant);
            }
        }

        // Return mocked port data when SNMP is unavailable
        return $this->mockPorts($olt, $variant);
    }

    /**
     * Parse snmpwalk output into port array.
     */
    protected function parseSnmpPorts(string $output, string $variant): array
    {
        $ports = [];
        $lines = explode("\n", trim($output));

        foreach ($lines as $line) {
            if (preg_match('/(\d+)\s*=\s*(\w+):\s*(.+)/', $line, $matches)) {
                $ports[] = [
                    'index' => $matches[1],
                    'type' => $variant,
                    'oper_status' => str_contains($matches[2], 'Up') ? 'up' : 'down',
                    'description' => trim($matches[3]),
                ];
            }
        }

        return $ports;
    }

    /**
     * Generate mocked port data for OLT simulators or when SNMP is unavailable.
     */
    protected function mockPorts(Olt $olt, string $variant): array
    {
        $totalPorts = $olt->total_onu ?? 16;
        $ports = [];

        for ($i = 1; $i <= min($totalPorts, 16); $i++) {
            $ports[] = [
                'port_id' => $i,
                'type' => $variant === 'gpon' ? 'GPON' : 'XGS-PON',
                'status' => $i <= 2 ? 'up' : 'unknown',
                'onu_count' => $i <= 2 ? random_int(10, 60) : 0,
                'description' => "Port {$i} – " . $olt->nama,
                'RxPower' => $i <= 2 ? round(random_int(-250, -200) / 10, 1) . ' dBm' : 'N/A',
            ];
        }

        return $ports;
    }
}
