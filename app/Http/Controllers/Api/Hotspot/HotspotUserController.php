<?php

namespace App\Http\Controllers\Api\Hotspot;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Hotspot\BulkDestroyHotspotUserRequest;
use App\Http\Requests\Hotspot\BulkEditHotspotUserRequest;
use App\Http\Requests\Hotspot\BulkReactivateHotspotUserRequest;
use App\Http\Requests\Hotspot\GenerateVoucherRequest;
use App\Http\Requests\Hotspot\ImportHotspotUserRequest;
use App\Http\Requests\Hotspot\StoreHotspotUserRequest;
use App\Models\HotspotUser;
use App\Models\Profile;
use App\Models\Reseller;
use App\Services\RadiusSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class HotspotUserController extends BaseApiController
{
    public function __construct(
        private readonly RadiusSyncService $radiusSync,
    ) {}

    public function ajax(Request $request): JsonResponse
    {
        $query = HotspotUser::with([
            'profile:id,nama,tipe,harga_jual',
            'nas:id,nama,ip_address',
            'reseller:id,nama,username',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('id_profile')) {
            $query->where('id_profile', $request->input('id_profile'));
        }
        if ($request->filled('id_reseller')) {
            $query->where('id_reseller', $request->input('id_reseller'));
        }
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('nama', 'like', "%{$search}%")
                    ->orWhere('nik', 'like', "%{$search}%")
                    ->orWhere('whatsapp', 'like', "%{$search}%");
            });
        }

        $length = (int) $request->input('length', 25);
        $start = (int) $request->input('start', 0);
        $page = ($start / $length) + 1;

        $paginator = $query->orderByDesc('id')->paginate($length, ['*'], 'page', $page);

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => HotspotUser::count(),
            'recordsFiltered' => $query->count(),
            'data' => $paginator->items(),
        ]);
    }

    public function store(StoreHotspotUserRequest $request): JsonResponse
    {
        try {
            $user = HotspotUser::create($request->validated());

            return $this->ok([
                'id' => $user->id,
                'username' => $user->username,
            ], 'User hotspot berhasil dibuat');
        } catch (\Exception $e) {
            Log::error('HotspotUser store failed: ' . $e->getMessage());
            return $this->error('Gagal membuat user: ' . $e->getMessage(), 500);
        }
    }

    public function bulkDestroy(BulkDestroyHotspotUserRequest $request): JsonResponse
    {
        $ids = $request->input('ids');

        try {
            $usernames = HotspotUser::whereIn('id', $ids)->pluck('username');

            DB::transaction(function () use ($ids) {
                foreach ($ids as $id) {
                    $user = HotspotUser::find($id);
                    if ($user) {
                        $user->delete();
                    }
                }
            });

            foreach ($usernames as $username) {
                try {
                    $this->radiusSync->removeUser($username);
                } catch (\Exception $e) {
                    Log::warning("Failed to remove RADIUS entry for {$username}: {$e->getMessage()}");
                }
            }

            return $this->ok(['deleted' => count($ids)], 'Berhasil menghpus ' . count($ids) . ' user');
        } catch (\Exception $e) {
            Log::error('Bulk destroy hotspot users failed: ' . $e->getMessage());
            return $this->error('Gagal menghapus user secara massal: ' . $e->getMessage(), 500);
        }
    }

    public function bulkEdit(BulkEditHotspotUserRequest $request): JsonResponse
    {
        $ids = $request->input('ids');
        $data = $request->validated();
        unset($data['ids']);

        if (empty($data)) {
            return $this->error('Tidak ada field yang diubah', 422);
        }

        $data = array_filter($data, fn($v) => $v !== null);

        try {
            $updated = HotspotUser::whereIn('id', $ids)->update($data);

            return $this->ok(['updated' => $updated], "Berhasil mengubah {$updated} user");
        } catch (\Exception $e) {
            Log::error('Bulk edit hotspot users failed: ' . $e->getMessage());
            return $this->error('Gagal mengubah user secara massal: ' . $e->getMessage(), 500);
        }
    }

    public function bulkResetMac(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:hotspot_users,id'],
        ]);

        try {
            $updated = HotspotUser::whereIn('id', $request->input('ids'))
                ->update(['mac_address' => null]);

            return $this->ok(['updated' => $updated], "Berhasil mereset MAC untuk {$updated} user");
        } catch (\Exception $e) {
            Log::error('Bulk reset MAC failed: ' . $e->getMessage());
            return $this->error('Gagal mereset MAC: ' . $e->getMessage(), 500);
        }
    }

    public function bulkResetCounter(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:hotspot_users,id'],
        ]);

        try {
            DB::table('hotspot_sessions')
                ->whereIn('username', HotspotUser::whereIn('id', $request->input('ids'))->pluck('username'))
                ->update([
                    'input_octets' => 0,
                    'output_octets' => 0,
                    'session_time' => 0,
                ]);

            return $this->ok(['reset' => count($request->input('ids'))], 'Berhasil mereset counter usage');
        } catch (\Exception $e) {
            Log::error('Bulk reset counter failed: ' . $e->getMessage());
            return $this->error('Gagal mereset counter: ' . $e->getMessage(), 500);
        }
    }

    public function bulkReactivate(BulkReactivateHotspotUserRequest $request): JsonResponse
    {
        $ids = $request->input('ids');
        $billingCycle = $request->input('billing_cycle', 'monthly');
        $startDate = $request->input('start_date') ? now()->parse($request->input('start_date')) : now();

        try {
            $users = HotspotUser::with('profile')->whereIn('id', $ids)->get();
            $updated = 0;

            DB::transaction(function () use ($users, $billingCycle, $startDate, &$updated) {
                foreach ($users as $user) {
                    $nextInvoice = match ($billingCycle) {
                        'weekly' => $startDate->copy()->addWeek(),
                        default => $startDate->copy()->addMonth(),
                    };

                    $user->update([
                        'status' => 'aktif',
                        'jatuh_tempo' => $nextInvoice->copy()->subDays(3),
                        'next_invoice' => $nextInvoice,
                    ]);
                    $updated++;
                }
            });

            return $this->ok(['updated' => $updated], "Berhasil mengaktifkan ulang {$updated} user");
        } catch (\Exception $e) {
            Log::error('Bulk reactivate failed: ' . $e->getMessage());
            return $this->error('Gagal mengaktifkan ulang user: ' . $e->getMessage(), 500);
        }
    }

    public function generateVoucher(GenerateVoucherRequest $request): JsonResponse
    {
        try {
            $jumlah = $request->input('jumlah');
            $prefix = $request->input('prefix', 'VC');
            $passwordLength = $request->input('password_length', 6);
            $tanggalAktif = $request->filled('tanggal_aktif') ? now()->parse($request->input('tanggal_aktif')) : now();
            $masaBerlaku = $request->filled('masa_berlaku') ? now()->parse($request->input('masa_berlaku')) : null;
            $enableBilling = $request->input('enable_billing', false);

            $profile = Profile::findOrFail($request->input('id_profile'));

            $vouchers = [];
            $created = [];

            DB::transaction(function () use ($jumlah, $prefix, $passwordLength, $tanggalAktif, $masaBerlaku, $enableBilling, $profile, $request, &$vouchers, &$created) {
                for ($i = 0; $i < $jumlah; $i++) {
                    $username = $prefix . strtoupper(Str::random(4)) . random_int(100, 999);
                    $password = Str::random($passwordLength);

                    $user = HotspotUser::create([
                        'id_profile' => $profile->id,
                        'id_reseller' => $request->input('id_reseller'),
                        'username' => $username,
                        'password' => $password,
                        'nama' => $username,
                        'status' => 'aktif',
                        'jatuh_tempo' => $masaBerlaku ? $masaBerlaku->copy()->subDays(3) : null,
                        'next_invoice' => $masaBerlaku,
                        'enable_billing' => $enableBilling,
                        'harga_paket' => $profile->harga_jual,
                    ]);

                    $created[] = $user;
                    $vouchers[] = [
                        'id' => $user->id,
                        'username' => $username,
                        'password' => $password,
                    ];
                }
            });

            return $this->ok(['vouchers' => $vouchers, 'count' => count($vouchers)], "Berhasil generate {$jumlah} voucher");
        } catch (\Exception $e) {
            Log::error('Generate voucher failed: ' . $e->getMessage());
            return $this->error('Gagal generate voucher: ' . $e->getMessage(), 500);
        }
    }

    public function print(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:hotspot_users,id'],
        ]);

        $vouchers = HotspotUser::with('profile:id,nama,harga_jual')
            ->whereIn('id', $request->input('ids'))
            ->get()
            ->map(fn($user) => [
                'id' => $user->id,
                'username' => $user->username,
                'password' => $user->getAttributes()['password'] ?? null,
                'nama' => $user->nama,
                'paket' => $user->profile?->nama,
                'harga' => $user->profile?->harga_jual,
                'jatuh_tempo' => $user->jatuh_tempo?->format('d-m-Y'),
            ]);

        return $this->ok($vouchers, 'Data voucher siap dicetak');
    }

    public function detail(Request $request): JsonResponse
    {
        $request->validate([
            'id' => ['required', 'integer', 'exists:hotspot_users,id'],
        ]);

        $user = HotspotUser::with([
            'profile', 'nas:id,nama,ip_address', 'pop:id,nama',
            'reseller:id,nama,username,whatsapp,saldo',
        ])->findOrFail($request->input('id'));

        $sessions = DB::table('radacct')
            ->where('username', $user->username)
            ->whereNull('acctstoptime')
            ->orderBy('acctstarttime', 'desc')
            ->limit(10)
            ->get();

        return $this->ok([
            'user' => $user,
            'active_sessions' => $sessions,
        ]);
    }

    public function credentials(Request $request): JsonResponse
    {
        $request->validate([
            'id' => ['required', 'integer', 'exists:hotspot_users,id'],
        ]);

        $user = HotspotUser::findOrFail($request->input('id'));
        $password = $user->getAttributes()['password'] ?? null;

        return $this->ok([
            'username' => $user->username,
            'password' => $password,
            'nama' => $user->nama,
        ]);
    }

    public function resellerMeta(Request $request): JsonResponse
    {
        $request->validate([
            'id' => ['required', 'integer', 'exists:resellers,id'],
        ]);

        $reseller = Reseller::with([
            'outlets',
            'deposits' => fn($q) => $q->orderByDesc('id')->limit(5),
        ])->findOrFail($request->input('id'));

        $stats = [
            'total_users' => HotspotUser::where('id_reseller', $request->input('id'))->count(),
            'users_aktif' => HotspotUser::where('id_reseller', $request->input('id'))->where('status', 'aktif')->count(),
            'users_suspend' => HotspotUser::where('id_reseller', $request->input('id'))->where('status', 'suspend')->count(),
            'total_saldo' => $reseller->saldo,
            'limit_hutang' => $reseller->limit_hutang,
        ];

        return $this->ok([
            'reseller' => $reseller,
            'stats' => $stats,
        ]);
    }

    public function stats(): JsonResponse
    {
        $stats = [
            'total' => HotspotUser::count(),
            'aktif' => HotspotUser::where('status', 'aktif')->count(),
            'suspend' => HotspotUser::where('status', 'suspend')->count(),
            'nonaktif' => HotspotUser::where('status', 'nonaktif')->count(),
            'jatuh_tempo_hari_ini' => HotspotUser::whereDate('jatuh_tempo', now()->toDateString())->count(),
            'jatuh_tempo_minggu_ini' => HotspotUser::whereBetween('jatuh_tempo', [
                now()->startOfWeek()->toDateString(),
                now()->endOfWeek()->toDateString(),
            ])->count(),
            'online_sekarang' => DB::table('radacct')
                ->whereNull('acctstoptime')
                ->whereIn('username', HotspotUser::where('status', 'aktif')->pluck('username'))
                ->count(),
            'total_pendapatan' => HotspotUser::where('enable_billing', true)->sum('harga_paket'),
        ];

        return $this->ok($stats);
    }

    public function setting(Request $request, string $mode = 'show'): JsonResponse
    {
        if ($request->isMethod('get') || $mode === 'show' || $request->input('_method') === 'GET') {
            $setting = [
                'default_prefix' => config('hotspot.default_prefix', 'VC'),
                'default_password_length' => config('hotspot.default_password_length', 6),
                'enable_billing_default' => config('hotspot.enable_billing_default', true),
                'jatuh_tempo_default_days' => config('hotspot.jatuh_tempo_default_days', 3),
            ];

            return $this->ok($setting);
        }

        $request->validate([
            'default_prefix' => ['nullable', 'string', 'max:10'],
            'default_password_length' => ['nullable', 'integer', 'min:4', 'max:12'],
            'enable_billing_default' => ['nullable', 'boolean'],
            'jatuh_tempo_default_days' => ['nullable', 'integer', 'min:0', 'max:30'],
        ]);

        foreach ($request->only(array_keys($request->rules())) as $key => $value) {
            if ($value !== null) {
                config(["hotspot.{$key}" => $value]);
            }
        }

        return $this->ok(null, 'Setting berhasil disimpan');
    }

    public function import(ImportHotspotUserRequest $request): JsonResponse
    {
        try {
            $file = $request->file('file');
            $rows = $this->parseFile($file);

            $imported = 0;
            $errors = [];

            DB::transaction(function () use ($rows, $request, &$imported, &$errors) {
                foreach ($rows as $index => $row) {
                    if (empty($row['username']) || empty($row['password'])) {
                        $errors[] = "Baris " . ($index + 2) . ": username atau password kosong";
                        continue;
                    }

                    try {
                        HotspotUser::create([
                            'id_profile' => $request->input('id_profile'),
                            'id_nas' => $request->input('id_nas'),
                            'id_reseller' => $request->input('id_reseller'),
                            'username' => trim($row['username']),
                            'password' => trim($row['password']),
                            'nama' => trim($row['nama'] ?? $row['username']),
                            'nik' => trim($row['nik'] ?? ''),
                            'whatsapp' => trim($row['whatsapp'] ?? ''),
                            'alamat' => trim($row['alamat'] ?? ''),
                            'status' => 'aktif',
                        ]);
                        $imported++;
                    } catch (\Exception $e) {
                        $errors[] = "Baris " . ($index + 2) . ": " . $e->getMessage();
                    }
                }
            });

            return $this->ok([
                'imported' => $imported,
                'errors' => $errors,
            ], "Berhasil import {$imported} user");
        } catch (\Exception $e) {
            Log::error('Import hotspot users failed: ' . $e->getMessage());
            return $this->error('Gagal import user: ' . $e->getMessage(), 500);
        }
    }

    private function parseFile($file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === 'csv' || $extension === 'txt') {
            return $this->parseCsv($file);
        }

        if ($extension === 'xlsx') {
            return $this->parseExcel($file);
        }

        throw new \InvalidArgumentException("Format file tidak didukung: {$extension}");
    }

    private function parseCsv($file): array
    {
        $rows = [];
        $handle = fopen($file->getRealPath(), 'r');
        $headers = fgetcsv($handle);

        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($headers, $row);
            $rows[] = $data;
        }
        fclose($handle);

        return $rows;
    }

    private function parseExcel($file): array
    {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        if (empty($rows)) {
            return [];
        }

        $headers = array_map('trim', $rows[0]);
        unset($rows[0]);

        return array_map(fn($row) => array_combine($headers, array_map('trim', $row)), array_values($rows));
    }
}
