<?php

namespace App\Http\Controllers\Api\Pppoe;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\Pppoe\StorePppoeUserRequest;
use App\Http\Requests\Api\Pppoe\BulkActionRequest;
use App\Models\PppoeUser;
use App\Models\PppoeSession;
use App\Models\Profile;
use App\Models\Odp;
use App\Models\PopArea;
use App\Services\RadiusSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PppoeUserController extends BaseApiController
{
    public function __construct(
        protected RadiusSyncService $radiusSync
    ) {}

    /**
     * GET /pppoe/user/ajax
     * DataTable paginated list with search/filter.
     */
    public function ajax(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('length', 25), 100);
        $page = (int) $request->get('start', 0) > 0
            ? (int) floor($request->get('start', 0) / $perPage) + 1
            : 1;

        $query = PppoeUser::query()
            ->with(['profile:id,nama,harga_jual,group_name', 'pop:id,nama', 'reseller:id,nama'])
            ->when($request->get('search'), function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('username', 'like', "%{$search}%")
                        ->orWhere('nama', 'like', "%{$search}%")
                        ->orWhere('whatsapp', 'like', "%{$search}%")
                        ->orWhere('alamat', 'like', "%{$search}%");
                });
            })
            ->when($request->get('status'), fn($q) => $q->where('status', $request->get('status')))
            ->when($request->get('id_profile'), fn($q) => $q->where('id_profile', $request->get('id_profile')))
            ->when($request->get('id_pop'), fn($q) => $q->where('id_pop', $request->get('id_pop')))
            ->when($request->get('tipe_user'), fn($q) => $q->where('tipe_user', $request->get('tipe_user')))
            ->orderBy('id', 'desc');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return $this->paginate($paginator);
    }

    /**
     * GET /pppoe/user/stats
     * Returns total users and counts per status.
     */
    public function stats(): JsonResponse
    {
        $stats = PppoeUser::query()
            ->selectRaw("
                COUNT(*) as total,
                SUM(status = 'aktif') as aktif,
                SUM(status = 'suspend') as suspend,
                SUM(status = 'nonaktif') as nonaktif,
                SUM(tipe_user = 'pppoe') as pppoe_count,
                SUM(tipe_user = 'dhcp') as dhcp_count
            ")
            ->first();

        return $this->ok([
            'total' => (int) $stats->total,
            'aktif' => (int) $stats->aktif,
            'suspend' => (int) $stats->suspend,
            'nonaktif' => (int) $stats->nonaktif,
            'pppoe_count' => (int) $stats->pppoe_count,
            'dhcp_count' => (int) $stats->dhcp_count,
        ]);
    }

    /**
     * GET /pppoe/user/graph/user-monthly
     * Monthly user creation trend for line chart.
     */
    public function userMonthlyGraph(Request $request): JsonResponse
    {
        $months = (int) $request->get('months', 12);

        $data = PppoeUser::query()
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count")
            ->where('created_at', '>=', now()->subMonths($months))
            ->groupByRaw("DATE_FORMAT(created_at, '%Y-%m')")
            ->orderByRaw("DATE_FORMAT(created_at, '%Y-%m')")
            ->get();

        return $this->ok($data);
    }

    /**
     * POST /pppoe/user/odp-by-area
     * Given id_pop, return ODPs for that area.
     */
    public function odpByArea(Request $request): JsonResponse
    {
        $request->validate(['id_pop' => ['required', 'integer', 'exists:pop_areas,id']]);

        $odps = Odp::where('id_pop', $request->get('id_pop'))
            ->orderBy('nama')
            ->get(['id', 'nama', 'jumlah_port', 'port_terpakai']);

        return $this->ok($odps);
    }

    /**
     * POST /pppoe/user/store
     * Create new PPPoE user.
     * RadiusSyncService handles RADIUS sync via observer — no manual sync needed.
     */
    public function store(StorePppoeUserRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Set default status and billing if not provided
        $data['status'] = $data['status'] ?? 'aktif';
        $data['enable_billing'] = $data['enable_billing'] ?? true;

        // Generate kode unik for postpaid users
        if (($data['jenis_tagihan'] ?? null) === 'pascabayar' && empty($data['kode_unik'])) {
            $data['kode_unik'] = random_int(100, 999);
        }

        $user = PppoeUser::create($data);

        // Sync to RADIUS immediately after creation
        $this->radiusSync->syncPPPoEUser($user);

        Log::info('PPPoE: Created user', ['id' => $user->id, 'username' => $user->username]);

        return $this->ok(
            $user->load(['profile:id,nama,harga_jual', 'pop:id,nama', 'reseller:id,nama']),
            'User berhasil dibuat'
        );
    }

    /**
     * GET /pppoe/user/detail/:id
     * Get single user detail with profile info.
     */
    public function detail(int $id): JsonResponse
    {
        $user = PppoeUser::with([
            'profile',
            'pop',
            'odp:id,id_pop,nama,jumlah_port,port_terpakai',
            'reseller:id,nama,whatsapp',
            'nas:id,nama,ip_router',
        ])->find($id);

        if (!$user) {
            return $this->error('User tidak ditemukan', 404);
        }

        // Attach current session info
        $session = PppoeSession::where('username', $user->username)
            ->where('status', 1)
            ->first();

        return $this->ok([
            'user' => $user,
            'current_session' => $session,
        ]);
    }

    /**
     * PUT /pppoe/user/update/:id
     * Update user and sync to RADIUS.
     */
    public function update(StorePppoeUserRequest $request, int $id): JsonResponse
    {
        $user = PppoeUser::find($id);
        if (!$user) {
            return $this->error('User tidak ditemukan', 404);
        }

        $data = $request->validated();

        // If username is being changed, remove old entry from RADIUS first
        if (isset($data['username']) && $data['username'] !== $user->username) {
            $this->radiusSync->removeUser($user->username);
        }

        $user->update($data);
        $this->radiusSync->syncPPPoEUser($user->fresh());

        Log::info('PPPoE: Updated user', ['id' => $user->id]);

        return $this->ok(
            $user->load(['profile:id,nama,harga_jual', 'pop:id,nama', 'reseller:id,nama']),
            'User berhasil diperbarui'
        );
    }

    /**
     * POST /pppoe/user/bulk-update
     * Bulk update selected users (fields provided in request).
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:pppoe_users,id'],
            'id_profile' => ['nullable', 'integer', 'exists:profiles,id'],
            'id_nas' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:aktif,suspend,nonaktif'],
            'jatuh_tempo' => ['nullable', 'date'],
        ]);

        $updated = PppoeUser::whereIn('id', $validated['ids'])
            ->update(array_filter([
                'id_profile' => $validated['id_profile'] ?? null,
                'id_nas' => $validated['id_nas'] ?? null,
                'status' => $validated['status'] ?? null,
                'jatuh_tempo' => $validated['jatuh_tempo'] ?? null,
            ], fn($v) => $v !== null));

        // Re-sync affected users to RADIUS
        $users = PppoeUser::whereIn('id', $validated['ids'])->get();
        foreach ($users as $user) {
            $this->radiusSync->syncPPPoEUser($user);
        }

        Log::info('PPPoE: Bulk updated users', ['ids' => $validated['ids']]);

        return $this->ok(['updated' => $updated], 'User berhasil diperbarui secara massal');
    }

    /**
     * POST /pppoe/user/bulk-disable
     * Bulk disable (set status to nonaktif).
     */
    public function bulkDisable(BulkActionRequest $request): JsonResponse
    {
        $ids = $request->validated()['ids'];

        PppoeUser::whereIn('id', $ids)->update(['status' => 'nonaktif']);

        $users = PppoeUser::whereIn('id', $ids)->get();
        foreach ($users as $user) {
            $this->radiusSync->syncPPPoEUser($user);
        }

        Log::info('PPPoE: Bulk disabled users', ['ids' => $ids]);

        return $this->ok(['count' => count($ids)], 'User berhasil dinonaktifkan');
    }

    /**
     * POST /pppoe/user/bulk-enable
     * Bulk enable (set status to aktif).
     */
    public function bulkEnable(BulkActionRequest $request): JsonResponse
    {
        $ids = $request->validated()['ids'];

        PppoeUser::whereIn('id', $ids)->update(['status' => 'aktif']);

        $users = PppoeUser::whereIn('id', $ids)->get();
        foreach ($users as $user) {
            $this->radiusSync->syncPPPoEUser($user);
        }

        Log::info('PPPoE: Bulk enabled users', ['ids' => $ids]);

        return $this->ok(['count' => count($ids)], 'User berhasil diaktifkan');
    }

    /**
     * POST /pppoe/user/bulk-delete
     * Bulk delete selected users and remove from RADIUS.
     */
    public function bulkDelete(BulkActionRequest $request): JsonResponse
    {
        $ids = $request->validated()['ids'];

        $users = PppoeUser::whereIn('id', $ids)->get();
        foreach ($users as $user) {
            $this->radiusSync->removeUser($user->username);
        }

        $deleted = PppoeUser::whereIn('id', $ids)->delete();

        Log::info('PPPoE: Bulk deleted users', ['ids' => $ids]);

        return $this->ok(['deleted' => $deleted], 'User berhasil dihapus');
    }

    /**
     * POST /pppoe/user/bulk-suspend
     * Bulk suspend (set status to suspend).
     */
    public function bulkSuspend(BulkActionRequest $request): JsonResponse
    {
        $ids = $request->validated()['ids'];

        PppoeUser::whereIn('id', $ids)->update(['status' => 'suspend']);

        $users = PppoeUser::whereIn('id', $ids)->get();
        foreach ($users as $user) {
            $this->radiusSync->syncPPPoEUser($user);
        }

        Log::info('PPPoE: Bulk suspended users', ['ids' => $ids]);

        return $this->ok(['count' => count($ids)], 'User berhasil di-suspend');
    }

    /**
     * GET|POST /pppoe/user/setting
     * Read or save PPPoE user settings.
     */
    public function setting(Request $request): JsonResponse
    {
        if ($request->isMethod('GET')) {
            return $this->ok([
                'default_status' => config('pppoe.default_status', 'aktif'),
                'default_billing' => config('pppoe.enable_billing', true),
                'default_tipe_user' => config('pppoe.default_tipe_user', 'pppoe'),
                'default_jenis_tagihan' => config('pppoe.default_jenis_tagihan', 'prabayar'),
                'kode_unik_range' => config('pppoe.kode_unik_range', [100, 999]),
                'generate_kode_unik_auto' => config('pppoe.generate_kode_unik_auto', true),
            ]);
        }

        $data = $request->validate([
            'default_status' => ['nullable', 'in:aktif,suspend,nonaktif'],
            'default_billing' => ['nullable', 'boolean'],
            'default_tipe_user' => ['nullable', 'in:pppoe,dhcp'],
            'default_jenis_tagihan' => ['nullable', 'in:prabayar,pascabayar'],
        ]);

        foreach ($data as $key => $value) {
            config(["pppoe.{$key}" => $value]);
        }

        return $this->ok(null, 'Pengaturan berhasil disimpan');
    }

    /**
     * GET /pppoe/user/print/kartu
     * Return printable card view for a user.
     */
    public function printKartu(Request $request): JsonResponse
    {
        $request->validate([
            'id' => ['required', 'integer', 'exists:pppoe_users,id'],
        ]);

        $user = PppoeUser::with(['profile:id,nama,harga_jual,rate_limit'])->find($request->get('id'));

        return $this->ok([
            'username' => $user->username,
            'password' => $user->password,
            'nama' => $user->nama,
            'alamat' => $user->alamat,
            'whatsapp' => $user->whatsapp,
            'profile' => $user->profile?->nama,
            'rate_limit' => $user->profile?->rate_limit,
            'harga_paket' => $user->harga_paket,
            'jatuh_tempo' => $user->jatuh_tempo?->format('d/m/Y'),
        ]);
    }

    /**
     * GET /pppoe/user/print/stiker
     * Return sticker print data for users (batch).
     */
    public function printStiker(Request $request): JsonResponse
    {
        $ids = $request->validate([
            'ids' => ['nullable', 'array'],
            'ids.*' => ['integer', 'exists:pppoe_users,id'],
        ]);

        $query = PppoeUser::with(['profile:id,nama,rate_limit']);

        if (!empty($ids['ids'])) {
            $query->whereIn('id', $ids['ids']);
        } else {
            $query->where('status', 'aktif')->limit(50);
        }

        $users = $query->get([
            'id', 'username', 'password', 'nama', 'alamat', 'whatsapp',
            'harga_paket', 'id_profile',
        ]);

        return $this->ok($users);
    }

    /**
     * GET /pppoe/user/session/:id
     * Get session data (active and recent) for a user.
     */
    public function session(int $id): JsonResponse
    {
        $user = PppoeUser::find($id);
        if (!$user) {
            return $this->error('User tidak ditemukan', 404);
        }

        $sessions = PppoeSession::where('username', $user->username)
            ->orderBy('start_time', 'desc')
            ->limit(20)
            ->get();

        return $this->ok($sessions);
    }

    /**
     * GET /pppoe/user/traffic/:id
     * Get traffic summary from radacct for a user.
     */
    public function traffic(int $id): JsonResponse
    {
        $user = PppoeUser::find($id);
        if (!$user) {
            return $this->error('User tidak ditemukan', 404);
        }

        $traffic = DB::table('radacct')
            ->where('username', $user->username)
            ->selectRaw("
                DATE(acctstarttime) as date,
                SUM(acctinputoctets) as total_input,
                SUM(acctoutputoctets) as total_output,
                SUM(acctsessiontime) as total_seconds,
                COUNT(*) as session_count
            ")
            ->where('acctstarttime', '>=', now()->subDays(30))
            ->groupByRaw("DATE(acctstarttime)")
            ->orderByRaw("DATE(acctstarttime)")
            ->get();

        $totals = DB::table('radacct')
            ->where('username', $user->username)
            ->selectRaw("
                COALESCE(SUM(acctinputoctets), 0) as total_input,
                COALESCE(SUM(acctoutputoctets), 0) as total_output,
                COALESCE(SUM(acctsessiontime), 0) as total_seconds,
                COUNT(*) as total_sessions
            ")
            ->first();

        return $this->ok([
            'totals' => $totals,
            'daily' => $traffic,
        ]);
    }

    /**
     * POST /pppoe/user/import
     * Import PPPoE users from uploaded Excel/CSV file.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,xlsx,xls', 'max:10240'],
        ]);

        if (!class_exists('\Maatwebsite\Excel\Facades\Excel')) {
            return $this->error(
                'Package Maatwebsite\Excel belum terinstal. Jalankan: composer require maatwebsite/excel',
                422
            );
        }

        try {
            $import = new \App\Imports\PppoeUserImport;
            \Maatwebsite\Excel\Facades\Excel::import($import, $request->file('file'));

            return $this->ok([
                'imported' => $import->getImportedCount(),
                'failed' => $import->getFailedCount(),
            ], 'Import berhasil');
        } catch (\Exception $e) {
            return $this->error('Import gagal: ' . $e->getMessage(), 422);
        }
    }

    /**
     * GET /pppoe/user/export/xls
     * Export PPPoE users to Excel file.
     */
    public function exportXls(Request $request): JsonResponse
    {
        if (!class_exists('\Maatwebsite\Excel\Facades\Excel')) {
            return $this->error(
                'Package Maatwebsite\Excel belum terinstal. Jalankan: composer require maatwebsite/excel',
                422
            );
        }

        try {
            $ids = $request->get('ids');
            $users = PppoeUser::with(['profile', 'pop', 'reseller'])
                ->when($ids, fn($q) => $q->whereIn('id', explode(',', $ids)))
                ->get();

            $export = new \App\Exports\PppoeUserExport($users);

            $filename = 'pppoe-users-' . now()->format('Ymd_His') . '.xlsx';
            $path = storage_path("app/exports/{$filename}");

            \Maatwebsite\Excel\Facades\Excel::store($export, "exports/{$filename}");

            return $this->ok([
                'url' => url("storage/exports/{$filename}"),
                'filename' => $filename,
            ], 'Export berhasil');
        } catch (\Exception $e) {
            return $this->error('Export gagal: ' . $e->getMessage(), 422);
        }
    }

    /**
     * GET /pppoe/user/export/rsc
     * Export users to Mikrotik RSC script format.
     */
    public function exportRsc(Request $request): JsonResponse
    {
        $ids = $request->get('ids');
        $users = PppoeUser::with(['profile'])
            ->where('status', 'aktif')
            ->when($ids, fn($q) => $q->whereIn('id', explode(',', $ids)))
            ->get();

        if ($users->isEmpty()) {
            return $this->error('Tidak ada user aktif untuk diexport', 422);
        }

        $lines = ["# PPPoE Users Export - " . now()->format('Y-m-d H:i:s')];
        $lines[] = '/ppp secret';
        $lines[] = 'remove [find name="pppoe-export-temp"]';

        foreach ($users as $user) {
            $profileName = $user->profile?->group_name ?? 'default';
            $localAddress = config('pppoe.local_address', '10.10.10.1');

            $lines[] = "add name=\"{$user->username}\" password=\"{$user->password}\" profile=\"{$profileName}\" service=ppp local-address=\"{$localAddress}\" disabled=no";
        }

        $script = implode("\n", $lines);

        return response()->streamDownload(
            fn() => print($script),
            "pppoe-users-" . now()->format('Ymd_His') . ".rsc",
            ['Content-Type' => 'text/plain']
        );
    }

    /**
     * POST /pppoe/user/generate-kode-unik-all
     * Generate unique codes for all active postpaid users.
     */
    public function generateKodeUnikAll(): JsonResponse
    {
        $updated = PppoeUser::where('status', 'aktif')
            ->where('jenis_tagihan', 'pascabayar')
            ->whereNull('kode_unik')
            ->update(['kode_unik' => DB::raw("MOD(id + 97, 900) + 100")]);

        Log::info('PPPoE: Generated kode unik for users', ['updated' => $updated]);

        return $this->ok(['updated' => $updated], "Kode unik berhasil di-generate untuk {$updated} user");
    }
}