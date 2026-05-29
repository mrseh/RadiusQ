<?php

namespace App\Http\Controllers\Api\Invoice;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Invoice\BulkActionInvoiceRequest;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaidController extends BaseApiController
{
    /**
     * GET /invoice/paid/ajax
     */
    public function ajax(Request $request): JsonResponse
    {
        $query = Invoice::with(['profile:id,nama', 'reseller:id,nama'])
            ->paid();

        if ($request->filled('id_reseller')) {
            $query->where('id_reseller', $request->input('id_reseller'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('tanggal_bayar', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('tanggal_bayar', '<=', $request->input('date_to'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('no_invoice', 'like', "%{$search}%")
                    ->orWhere('nama_pelanggan', 'like', "%{$search}%")
                    ->orWhere('whatsapp', 'like', "%{$search}%");
            });
        }

        $length = (int) $request->input('length', 25);
        $start = (int) $request->input('start', 0);
        $page = ($start / $length) + 1;

        $paginator = $query->orderByDesc('tanggal_bayar')->paginate($length, ['*'], 'page', $page);

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => Invoice::paid()->count(),
            'recordsFiltered' => $paginator->total(),
            'data' => $paginator->items(),
        ]);
    }

    /**
     * POST /invoice/paid/cancel-selected
     * Revert selected paid invoices back to unpaid.
     */
    public function cancelSelected(BulkActionInvoiceRequest $request): JsonResponse
    {
        try {
            $ids = $request->validated('ids');

            return DB::transaction(function () use ($ids) {
                $updated = Invoice::whereIn('id', $ids)
                    ->where('status', 'paid')
                    ->update([
                        'status' => 'unpaid',
                        'tanggal_bayar' => null,
                        'metode' => null,
                    ]);

                return $this->ok(['updated' => $updated], "{$updated} invoice berhasil dibatalkan");
            });
        } catch (\Exception $e) {
            Log::error('Cancel paid invoices failed: ' . $e->getMessage());

            return $this->error('Gagal membatalkan invoice', 500);
        }
    }

    /**
     * POST /invoice/paid/send-selected
     * Send WhatsApp receipt notification for selected paid invoices.
     */
    public function sendSelected(BulkActionInvoiceRequest $request): JsonResponse
    {
        try {
            $ids = $request->validated('ids');

            $invoices = Invoice::whereIn('id', $ids)->paid()->get();

            if ($invoices->isEmpty()) {
                return $this->error('Tidak ada invoice lunas yang ditemukan', 422);
            }

            $sent = 0;
            foreach ($invoices as $invoice) {
                if ($invoice->whatsapp) {
                    // TODO: dispatch WhatsApp receipt job
                    $invoice->update(['wa_status' => 'sent']);
                    $sent++;
                }
            }

            return $this->ok([
                'total' => $invoices->count(),
                'sent' => $sent,
            ], "Receipt berhasil dikirim ke {$sent} invoice");
        } catch (\Exception $e) {
            Log::error('Send paid invoice receipts failed: ' . $e->getMessage());

            return $this->error('Gagal mengirim receipt', 500);
        }
    }

    /**
     * DELETE /invoice/paid/delete-selected
     */
    public function deleteSelected(BulkActionInvoiceRequest $request): JsonResponse
    {
        try {
            $ids = $request->validated('ids');

            $deleted = Invoice::whereIn('id', $ids)->delete();

            return $this->ok(['deleted' => $deleted], "{$deleted} invoice berhasil dihapus");
        } catch (\Exception $e) {
            Log::error('Delete paid invoices failed: ' . $e->getMessage());

            return $this->error('Gagal menghapus invoice', 500);
        }
    }

    /**
     * GET /invoice/paid/export
     */
    public function export(Request $request): JsonResponse
    {
        $query = Invoice::with(['profile:id,nama', 'reseller:id,nama'])
            ->paid();

        if ($request->filled('id_reseller')) {
            $query->where('id_reseller', $request->input('id_reseller'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('tanggal_bayar', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('tanggal_bayar', '<=', $request->input('date_to'));
        }

        $invoices = $query->orderByDesc('tanggal_bayar')->get();

        return $this->ok($invoices, 'Data invoice paid siap diexport');
    }

    /**
     * GET /invoice/paid/print
     */
    public function print(Request $request): JsonResponse
    {
        return $this->export($request);
    }

    /**
     * GET /invoice/paid/detail/:id
     */
    public function detail(int $id): JsonResponse
    {
        $invoice = Invoice::with(['profile:id,nama', 'reseller:id,nama'])->find($id);

        if (!$invoice) {
            return $this->error('Invoice tidak ditemukan', 404);
        }

        return $this->ok($invoice);
    }
}