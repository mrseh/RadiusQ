<?php

namespace App\Http\Controllers\Api\Tiket;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\Tiket\BulkActionRequest;
use App\Http\Requests\Api\Tiket\StoreTiketRequest;
use App\Models\TiketGangguan;
use App\Models\User;
use App\Models\PppoeUser;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TiketGangguanController extends BaseApiController
{
    /**
     * GET /tiket/gangguan/ajax
     * DataTable with status and prioritas filter.
     */
    public function ajax(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('length', 25), 100);
        $page = (int) $request->get('start', 0) > 0
            ? (int) floor($request->get('start', 0) / $perPage) + 1
            : 1;

        $query = TiketGangguan::query()
            ->with(['user:id,name,role', 'teknisi:id,name'])
            ->when($request->get('status'), fn($q) => $q->where('status', $request->get('status')))
            ->when($request->get('prioritas'), fn($q) => $q->where('prioritas', $request->get('prioritas')))
            ->when($request->get('search'), fn($q) => $q->where('nomor_tiket', 'like', '%' . $request->get('search') . '%')
                ->orWhere('nama_pelanggan', 'like', '%' . $request->get('search') . '%'))
            ->when($request->get('teknisi_id'), fn($q) => $q->where('teknisi_id', $request->get('teknisi_id')))
            ->orderBy('created_at', 'desc');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return $this->paginate($paginator);
    }

    /**
     * GET /tiket/gangguan/stats
     * Ticket statistics.
     */
    public function stats(): JsonResponse
    {
        $stats = TiketGangguan::query()
            ->selectRaw("
                COUNT(*) as total,
                SUM(status = 'open') as open,
                SUM(status = 'progress') as progress,
                SUM(status = 'proses') as proses,
                SUM(status = 'resolved') as resolved,
                SUM(status = 'closed') as closed,
                SUM(prioritas = 'critical') as critical,
                SUM(prioritas = 'high') as high,
                SUM(prioritas = 'medium') as medium,
                SUM(prioritas = 'low') as low
            ")
            ->first();

        return $this->ok([
            'total' => (int) $stats->total,
            'open' => (int) $stats->open + (int) $stats->proses,
            'progress' => (int) $stats->progress,
            'resolved' => (int) $stats->resolved,
            'closed' => (int) $stats->closed,
            'prioritas' => [
                'critical' => (int) $stats->critical,
                'high' => (int) $stats->high,
                'medium' => (int) $stats->medium,
                'low' => (int) $stats->low,
            ],
        ]);
    }

    /**
     * POST /tiket/gangguan
     * Create a new ticket.
     */
    public function store(StoreTiketRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['id_user'] = $request->user()->id;
        $data['user_type'] = $request->user()->role;
        $data['status'] = 'open';
        $data['nomor_tiket'] = 'TKT-' . strtoupper(now()->format('ymd')) . '-' . str_pad(TiketGangguan::count() + 1, 4, '0', STR_PAD_LEFT);

        $tiket = TiketGangguan::create($data);

        Log::info('Tiket: Created', ['id' => $tiket->id, 'nomor_tiket' => $tiket->nomor_tiket]);

        return $this->ok(
            $tiket->load(['user:id,name', 'teknisi:id,name']),
            'Tiket berhasil dibuat'
        );
    }

    /**
     * GET /tiket/gangguan/:id/ambil
     * Assign/claim ticket to current teknisi.
     */
    public function ambil(int $id): JsonResponse
    {
        $tiket = TiketGangguan::find($id);

        if (!$tiket) {
            return $this->error('Tiket tidak ditemukan', 404);
        }

        $user = auth()->user();

        $tiket->update([
            'teknisi_id' => $user->id,
            'nama_teknisi' => $user->name,
            'status' => 'progress',
        ]);

        Log::info('Tiket: Claimed by teknisi', [
            'tiket_id' => $tiket->id,
            'teknisi_id' => $user->id,
        ]);

        return $this->ok($tiket->fresh(), 'Tiket berhasil diambil');
    }

    /**
     * GET /tiket/gangguan/:id/tutup
     * Close a ticket.
     */
    public function tutup(int $id): JsonResponse
    {
        $tiket = TiketGangguan::find($id);

        if (!$tiket) {
            return $this->error('Tiket tidak ditemukan', 404);
        }

        $tiket->update([
            'status' => 'closed',
            'tanggal_selesai' => now(),
        ]);

        Log::info('Tiket: Closed', ['id' => $tiket->id]);

        return $this->ok($tiket->fresh(), 'Tiket berhasil ditutup');
    }

    /**
     * POST /tiket/gangguan/bulk/open
     * Bulk open tickets.
     */
    public function bulkOpen(BulkActionRequest $request): JsonResponse
    {
        $ids = $request->validated()['ids'];
        $updated = TiketGangguan::whereIn('id', $ids)->update(['status' => 'open']);

        Log::info('Tiket: Bulk opened', ['ids' => $ids]);

        return $this->ok(['updated' => $updated], "{$updated} tiket berhasil dibuka");
    }

    /**
     * POST /tiket/gangguan/bulk/progress
     * Bulk set tickets to progress.
     */
    public function bulkProgress(BulkActionRequest $request): JsonResponse
    {
        $ids = $request->validated()['ids'];
        $updated = TiketGangguan::whereIn('id', $ids)->update(['status' => 'progress']);

        Log::info('Tiket: Bulk set to progress', ['ids' => $ids]);

        return $this->ok(['updated' => $updated], "{$updated} tiket berhasil diproses");
    }

    /**
     * POST /tiket/gangguan/bulk/close
     * Bulk close tickets.
     */
    public function bulkClose(BulkActionRequest $request): JsonResponse
    {
        $ids = $request->validated()['ids'];
        $updated = TiketGangguan::whereIn('id', $ids)->update([
            'status' => 'closed',
            'tanggal_selesai' => now(),
        ]);

        Log::info('Tiket: Bulk closed', ['ids' => $ids]);

        return $this->ok(['updated' => $updated], "{$updated} tiket berhasil ditutup");
    }

    /**
     * POST /tiket/gangguan/bulk/delete
     * Bulk delete tickets.
     */
    public function bulkDelete(BulkActionRequest $request): JsonResponse
    {
        $ids = $request->validated()['ids'];
        $deleted = TiketGangguan::whereIn('id', $ids)->delete();

        Log::info('Tiket: Bulk deleted', ['ids' => $ids]);

        return $this->ok(['deleted' => $deleted], "{$deleted} tiket berhasil dihapus");
    }

    /**
     * GET /tiket/gangguan/:id/detail
     * Ticket detail with customer and connection info.
     */
    public function detail(int $id): JsonResponse
    {
        $tiket = TiketGangguan::with(['user:id,name,role', 'teknisi:id,name'])->find($id);

        if (!$tiket) {
            return $this->error('Tiket tidak ditemukan', 404);
        }

        // Find pelanggan by name or related user
        $pelanggan = null;

        if ($tiket->id_user) {
            $pelanggan = PppoeUser::where('nama', 'like', '%' . $tiket->nama_pelanggan . '%')
                ->orWhere('username', $tiket->nama_pelanggan)
                ->first();
        }

        $invoice = $pelanggan
            ? Invoice::where('id_pelanggan', $pelanggan->id)->orderByDesc('tanggal_invoice')->first()
            : null;

        return $this->ok([
            'tiket' => $tiket,
            'pelanggan' => $pelanggan,
            'invoice_terakhir' => $invoice,
        ]);
    }

    /**
     * GET /tiket/gangguan/pelanggan/search
     * Search pelanggan.
     */
    public function searchPelanggan(Request $request): JsonResponse
    {
        $search = $request->get('search', '');

        if (strlen($search) < 2) {
            return $this->ok([]);
        }

        $pelanggan = PppoeUser::query()
            ->select(['id', 'username', 'nama', 'alamat', 'whatsapp', 'status', 'id_profile'])
            ->where('nama', 'like', '%' . $search . '%')
            ->orWhere('username', 'like', '%' . $search . '%')
            ->orWhere('whatsapp', 'like', '%' . $search . '%')
            ->limit(20)
            ->get();

        return $this->ok($pelanggan);
    }

    /**
     * GET /tiket/gangguan/pelanggan/:id/conn
     * Get pelanggan connection info (active session, package).
     */
    public function pelangganConn(int $id): JsonResponse
    {
        $pelanggan = PppoeUser::with(['profile:id,nama,harga_jual,group_name'])->find($id);

        if (!$pelanggan) {
            return $this->error('Pelanggan tidak ditemukan', 404);
        }

        $session = DB::table('radacct')
            ->where('username', $pelanggan->username)
            ->where('acctstoptime', null)
            ->orderByDesc('acctstarttime')
            ->first();

        $totalTraffic = DB::table('radacct')
            ->where('username', $pelanggan->username)
            ->selectRaw('COALESCE(SUM(acctinputoctets), 0) as total_input, COALESCE(SUM(acctoutputoctets), 0) as total_output')
            ->first();

        return $this->ok([
            'pelanggan' => $pelanggan,
            'active_session' => $session,
            'profile' => $pelanggan->profile,
            'total_input_bytes' => $totalTraffic->total_input ?? 0,
            'total_output_bytes' => $totalTraffic->total_output ?? 0,
        ]);
    }

    /**
     * GET /tiket/gangguan/getTeknisi
     * List all teknisi users.
     */
    public function getTeknisi(): JsonResponse
    {
        $teknisi = User::query()
            ->where('role', 'teknisi')
            ->where('status', 'aktif')
            ->orderBy('name')
            ->get(['id', 'name', 'whatsapp']);

        return $this->ok($teknisi);
    }
}