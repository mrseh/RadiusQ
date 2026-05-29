<?php

namespace App\Http\Controllers\Api\Transaksi;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\Transaksi\BulkDeleteRequest;
use App\Http\Requests\Api\Transaksi\StoreTransaksiRequest;
use App\Models\Reseller;
use App\Models\Transaksi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransaksiController extends BaseApiController
{
    /**
     * GET /transaksi
     * Base view — return paginated list.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('length', 25), 100);
        $page = (int) $request->get('start', 0) > 0
            ? (int) floor($request->get('start', 0) / $perPage) + 1
            : 1;

        $query = Transaksi::query()
            ->with(['reseller:id,nama'])
            ->when($request->get('search'), fn($q) => $q->where('deskripsi', 'like', '%' . $request->get('search') . '%')
                ->orWhere('kategori', 'like', '%' . $request->get('search') . '%'))
            ->when($request->get('jenis'), fn($q) => $q->where('jenis', $request->get('jenis')))
            ->when($request->get('id_reseller'), fn($q) => $q->where('id_reseller', $request->get('id_reseller')))
            ->orderBy('tanggal', 'desc');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return $this->paginate($paginator);
    }

    /**
     * GET /transaksi/ajax
     * DataTable with date range filter and reseller filter.
     */
    public function ajax(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('length', 25), 100);
        $page = (int) $request->get('start', 0) > 0
            ? (int) floor($request->get('start', 0) / $perPage) + 1
            : 1;

        $query = Transaksi::query()
            ->with(['reseller:id,nama'])
            ->when($request->get('search'), fn($q) => $q->where('deskripsi', 'like', '%' . $request->get('search') . '%')
                ->orWhere('kategori', 'like', '%' . $request->get('search') . '%'))
            ->when($request->get('jenis'), fn($q) => $q->where('jenis', $request->get('jenis')))
            ->when($request->get('id_reseller'), fn($q) => $q->where('id_reseller', $request->get('id_reseller')))
            ->when($request->get('date_from'), fn($q) => $q->whereDate('tanggal', '>=', $request->get('date_from')))
            ->when($request->get('date_to'), fn($q) => $q->whereDate('tanggal', '<=', $request->get('date_to')))
            ->orderBy('tanggal', 'desc');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return $this->paginate($paginator);
    }

    /**
     * POST /transaksi/store
     * Create deposit / transaction.
     */
    public function store(StoreTransaksiRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['tanggal'] = $data['tanggal'] ?? now()->toDateString();

        $transaksi = Transaksi::create($data);

        // Update reseller saldo if linked and it's a pemasukan
        if (!empty($data['id_reseller']) && $data['jenis'] === 'pemasukan') {
            Reseller::where('id', $data['id_reseller'])->increment('saldo', $data['nominal']);
        }

        Log::info('Transaksi: Created', ['id' => $transaksi->id, 'jenis' => $transaksi->jenis]);

        return $this->ok(
            $transaksi->load(['reseller:id,nama']),
            'Transaksi berhasil disimpan'
        );
    }

    /**
     * GET /transaksi/export/csv
     * Export transactions to CSV.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $query = Transaksi::query()
            ->with(['reseller:id,nama'])
            ->when($request->get('date_from'), fn($q) => $q->whereDate('tanggal', '>=', $request->get('date_from')))
            ->when($request->get('date_to'), fn($q) => $q->whereDate('tanggal', '<=', $request->get('date_to')))
            ->when($request->get('jenis'), fn($q) => $q->where('jenis', $request->get('jenis')))
            ->when($request->get('id_reseller'), fn($q) => $q->where('id_reseller', $request->get('id_reseller')))
            ->orderBy('tanggal', 'desc');

        $filename = 'transaksi-' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['ID', 'Tanggal', 'Reseller', 'Jenis', 'Kategori', 'Deskripsi', 'Qty', 'Nominal']);

            $query->chunk(500, function ($rows) use ($handle) {
                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row->id,
                        $row->tanggal?->format('Y-m-d'),
                        $row->reseller?->nama ?? '-',
                        $row->jenis,
                        $row->kategori ?? '',
                        $row->deskripsi ?? '',
                        $row->qty ?? '',
                        $row->nominal,
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * GET /transaksi/report/daily
     * Daily summary report.
     */
    public function dailyReport(Request $request): JsonResponse
    {
        $date = $request->get('date', now()->toDateString());

        $pemasukan = Transaksi::where('jenis', 'pemasukan')
            ->whereDate('tanggal', $date)
            ->sum('nominal');

        $pengeluaran = Transaksi::where('jenis', 'pengeluaran')
            ->whereDate('tanggal', $date)
            ->sum('nominal');

        $details = Transaksi::with(['reseller:id,nama'])
            ->whereDate('tanggal', $date)
            ->orderBy('jenis')
            ->get();

        return $this->ok([
            'date' => $date,
            'total_pemasukan' => round($pemasukan, 2),
            'total_pengeluaran' => round($pengeluaran, 2),
            'net_saldo' => round($pemasukan - $pengeluaran, 2),
            'detail' => $details,
        ]);
    }

    /**
     * GET /transaksi/report/monthly
     * Monthly summary report.
     */
    public function monthlyReport(Request $request): JsonResponse
    {
        $year = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);

        $start = now()->create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $pemasukan = Transaksi::where('jenis', 'pemasukan')
            ->whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
            ->sum('nominal');

        $pengeluaran = Transaksi::where('jenis', 'pengeluaran')
            ->whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
            ->sum('nominal');

        // Daily breakdown
        $daily = Transaksi::query()
            ->selectRaw("tanggal,
                SUM(CASE WHEN jenis = 'pemasukan' THEN nominal ELSE 0 END) as total_pemasukan,
                SUM(CASE WHEN jenis = 'pengeluaran' THEN nominal ELSE 0 END) as total_pengeluaran,
                COUNT(*) as jumlah_transaksi")
            ->whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
            ->groupByRaw('tanggal')
            ->orderByRaw('tanggal')
            ->get();

        return $this->ok([
            'year' => $year,
            'month' => $month,
            'total_pemasukan' => round($pemasukan, 2),
            'total_pengeluaran' => round($pengeluaran, 2),
            'net_saldo' => round($pemasukan - $pengeluaran, 2),
            'daily' => $daily,
        ]);
    }

    /**
     * DELETE /transaksi/empty
     * Clear all transactions (with confirmation).
     */
    public function empty(BulkDeleteRequest $request): JsonResponse
    {
        $count = Transaksi::count();
        Transaksi::truncate();

        Log::warning('Transaksi: All records cleared', ['count' => $count]);

        return $this->ok(['cleared' => $count], "{$count} transaksi berhasil dihapus");
    }

    /**
     * GET /transaksi/stats
     * Overview stats: total deposit, total payment, this month.
     */
    public function stats(): JsonResponse
    {
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $totalDeposit = Transaksi::where('jenis', 'pemasukan')->sum('nominal');
        $totalPayment = Transaksi::where('jenis', 'pengeluaran')->sum('nominal');

        $bulanIniPemasukan = Transaksi::where('jenis', 'pemasukan')
            ->whereBetween('tanggal', [$monthStart, $monthEnd])
            ->sum('nominal');

        $bulanIniPengeluaran = Transaksi::where('jenis', 'pengeluaran')
            ->whereBetween('tanggal', [$monthStart, $monthEnd])
            ->sum('nominal');

        $totalTransaksi = Transaksi::count();
        $transaksiBulanIni = Transaksi::whereBetween('tanggal', [$monthStart, $monthEnd])->count();

        return $this->ok([
            'total_pemasukan_all' => round($totalDeposit, 2),
            'total_pengeluaran_all' => round($totalPayment, 2),
            'saldo_neto_all' => round($totalDeposit - $totalPayment, 2),
            'bulan_ini_pemasukan' => round($bulanIniPemasukan, 2),
            'bulan_ini_pengeluaran' => round($bulanIniPengeluaran, 2),
            'bulan_ini_neto' => round($bulanIniPemasukan - $bulanIniPengeluaran, 2),
            'total_transaksi' => $totalTransaksi,
            'transaksi_bulan_ini' => $transaksiBulanIni,
        ]);
    }
}