<?php

namespace App\Http\Controllers\Api\Hotspot;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Hotspot\RefundVoucherRequest;
use App\Http\Requests\Hotspot\RekapVoucherRequest;
use App\Models\HotspotUser;
use App\Models\Reseller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HotspotVoucherController extends BaseApiController
{
    public function ajax(Request $request): JsonResponse
    {
        $query = HotspotUser::with(['profile:id,nama,harga_jual', 'reseller:id,nama,username'])
            ->whereNotNull('kode_unik');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('id_reseller')) {
            $query->where('id_reseller', $request->input('id_reseller'));
        }
        if ($request->filled('id_profile')) {
            $query->where('id_profile', $request->input('id_profile'));
        }
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('nama', 'like', "%{$search}%");
            });
        }

        $length = (int) $request->input('length', 25);
        $start = (int) $request->input('start', 0);
        $page = ($start / $length) + 1;

        $paginator = $query->orderByDesc('id')->paginate($length, ['*'], 'page', $page);

        $data = collect($paginator->items())->map(fn($user) => [
            'id' => $user->id,
            'username' => $user->username,
            'password' => $user->getAttributes()['password'] ?? null,
            'nama' => $user->nama,
            'paket' => $user->profile?->nama,
            'harga' => $user->profile?->harga_jual,
            'harga_paket' => $user->harga_paket,
            'reseller' => $user->reseller?->nama,
            'status' => $user->status,
            'jatuh_tempo' => $user->jatuh_tempo?->format('d-m-Y'),
            'next_invoice' => $user->next_invoice?->format('d-m-Y'),
            'created_at' => $user->created_at?->toIso8601String(),
        ]);

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => HotspotUser::whereNotNull('kode_unik')->count(),
            'recordsFiltered' => $paginator->total(),
            'data' => $data,
        ]);
    }

    public function stats(): JsonResponse
    {
        $stats = [
            'total_voucher' => HotspotUser::whereNotNull('kode_unik')->count(),
            'terjual' => HotspotUser::whereNotNull('kode_unik')->where('status', 'aktif')->count(),
            'terpakai' => HotspotUser::whereNotNull('kode_unik')->where('status', 'suspend')->count(),
            'hangus' => HotspotUser::whereNotNull('kode_unik')
                ->whereDate('jatuh_tempo', '<', now()->toDateString())
                ->where('status', '!=', 'aktif')
                ->count(),
            'total_nilai' => HotspotUser::whereNotNull('kode_unik')
                ->where('enable_billing', true)
                ->sum('harga_paket'),
            'per_reseller' => Reseller::withCount([
                'hotspotUsers as voucher_count' => fn($q) => $q->whereNotNull('kode_unik'),
            ])->whereHas('hotspotUsers', fn($q) => $q->whereNotNull('kode_unik'))
                ->get(['id', 'nama', 'username'])
                ->map(fn($r) => [
                    'id' => $r->id,
                    'nama' => $r->nama,
                    'username' => $r->username,
                    'voucher_count' => $r->voucher_count,
                ]),
        ];

        return $this->ok($stats);
    }

    public function detail(int $id): JsonResponse
    {
        $voucher = HotspotUser::with([
            'profile', 'reseller:id,nama,username,whatsapp',
            'nas:id,nama,ip_address',
        ])->findOrFail($id);

        $usageHistory = DB::table('radacct')
            ->where('username', $voucher->username)
            ->orderBy('acctstarttime', 'desc')
            ->limit(20)
            ->get();

        return $this->ok([
            'voucher' => $voucher,
            'usage_history' => $usageHistory,
        ]);
    }

    public function refund(RefundVoucherRequest $request, string $ids): JsonResponse
    {
        $idsArray = array_map('intval', explode(',', $ids));

        try {
            $updated = HotspotUser::whereIn('id', $idsArray)->update([
                'status' => 'nonaktif',
                'kode_unik' => null,
            ]);

            return $this->ok(['refunded' => $updated], "Berhasil refund {$updated} voucher");
        } catch (\Exception $e) {
            Log::error('Refund vouchers failed: ' . $e->getMessage());
            return $this->error('Gagal refund voucher: ' . $e->getMessage(), 500);
        }
    }

    public function export(Request $request): StreamedResponse|JsonResponse
    {
        $query = HotspotUser::with(['profile:id,nama', 'reseller:id,nama'])
            ->whereNotNull('kode_unik');

        if ($request->filled('id_reseller')) {
            $query->where('id_reseller', $request->input('id_reseller'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('tanggal_mulai')) {
            $query->whereDate('created_at', '>=', $request->input('tanggal_mulai'));
        }
        if ($request->filled('tanggal_selesai')) {
            $query->whereDate('created_at', '<=', $request->input('tanggal_selesai'));
        }

        $vouchers = $query->orderByDesc('id')->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="voucher-export-' . now()->format('Ymd-His') . '.csv"',
        ];

        $callback = function () use ($vouchers) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'ID', 'Username', 'Password', 'Nama', 'Paket',
                'Harga', 'Reseller', 'Status', ' Jatuh Tempo', 'Dibuat',
            ]);

            foreach ($vouchers as $v) {
                fputcsv($handle, [
                    $v->id,
                    $v->username,
                    $v->getAttributes()['password'] ?? '',
                    $v->nama,
                    $v->profile?->nama,
                    $v->harga_paket,
                    $v->reseller?->nama,
                    $v->status,
                    $v->jatuh_tempo?->format('d-m-Y'),
                    $v->created_at?->format('d-m-Y H:i'),
                ]);
            }

            fclose($handle);
        };

        return Response::stream($callback, 200, $headers);
    }

    public function rekap(RekapVoucherRequest $request): JsonResponse
    {
        $query = HotspotUser::with(['profile:id,nama', 'reseller:id,nama'])
            ->whereNotNull('kode_unik');

        if ($request->filled('tanggal_mulai')) {
            $query->whereDate('created_at', '>=', $request->input('tanggal_mulai'));
        }
        if ($request->filled('tanggal_selesai')) {
            $query->whereDate('created_at', '<=', $request->input('tanggal_selesai'));
        }
        if ($request->filled('id_reseller')) {
            $query->where('id_reseller', $request->input('id_reseller'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $vouchers = $query->get();

        $rekap = [
            'total_voucher' => $vouchers->count(),
            'total_nilai' => $vouchers->where('enable_billing', true)->sum('harga_paket'),
            'per_paket' => $vouchers->groupBy(fn($v) => $v->profile?->nama ?? 'Tanpa Paket')
                ->map(fn($group) => [
                    'count' => $group->count(),
                    'total_nilai' => $group->where('enable_billing', true)->sum('harga_paket'),
                ]),
            'per_status' => $vouchers->groupBy('status')
                ->map(fn($group) => $group->count()),
            'per_reseller' => $vouchers->groupBy(fn($v) => $v->reseller?->nama ?? 'Tanpa Reseller')
                ->map(fn($group) => $group->count()),
            'vouchers' => $vouchers,
        ];

        return $this->ok($rekap);
    }

    public function deleteExpired(Request $request): JsonResponse
    {
        $request->validate([
            'hari' => ['nullable', 'integer', 'min:1'],
        ]);

        $hari = $request->input('hari', 30);
        $cutoffDate = now()->subDays($hari);

        try {
            $deleted = HotspotUser::whereNotNull('kode_unik')
                ->where('status', '!=', 'aktif')
                ->whereDate('jatuh_tempo', '<', $cutoffDate->toDateString())
                ->delete();

            return $this->ok(['deleted' => $deleted], "Berhasil menghapus {$deleted} voucher expired");
        } catch (\Exception $e) {
            Log::error('Delete expired vouchers failed: ' . $e->getMessage());
            return $this->error('Gagal menghapus voucher expired: ' . $e->getMessage(), 500);
        }
    }
}
