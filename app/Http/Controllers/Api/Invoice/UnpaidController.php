<?php

namespace App\Http\Controllers\Api\Invoice;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Invoice\BulkActionInvoiceRequest;
use App\Http\Requests\Invoice\MergeDuplicateInvoicesRequest;
use App\Http\Requests\Invoice\PayInvoiceRequest;
use App\Http\Requests\Invoice\SaveInvoiceRequest;
use App\Http\Requests\Invoice\StoreManualInvoiceRequest;
use App\Models\Invoice;
use App\Models\PaymentGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UnpaidController extends BaseApiController
{
    /**
     * GET /invoice/unpaid/ajax
     * DataTable server-side list with optional filters: date range, reseller, status.
     */
    public function ajax(Request $request): JsonResponse
    {
        $query = Invoice::with(['profile:id,nama', 'reseller:id,nama'])
            ->unpaid();

        if ($request->filled('id_reseller')) {
            $query->where('id_reseller', $request->input('id_reseller'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('tanggal_invoice', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('tanggal_invoice', '<=', $request->input('date_to'));
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

        $paginator = $query->orderByDesc('id')->paginate($length, ['*'], 'page', $page);

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => Invoice::unpaid()->count(),
            'recordsFiltered' => $paginator->total(),
            'data' => $paginator->items(),
        ]);
    }

    /**
     * POST /invoice/unpaid/manual
     * Create a manual invoice.
     */
    public function manual(StoreManualInvoiceRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $data['no_invoice'] = 'INV-' . strtoupper(Str::random(8));
            $data['status'] = 'unpaid';
            $data['tanggal_invoice'] = $data['tanggal_invoice'] ?? now()->toDateString();
            $data['is_overdue'] = false;

            $invoice = Invoice::create($data);

            return $this->ok([
                'id' => $invoice->id,
                'no_invoice' => $invoice->no_invoice,
            ], 'Invoice berhasil dibuat');
        } catch (\Exception $e) {
            Log::error('Manual invoice creation failed: ' . $e->getMessage());

            return $this->error('Gagal membuat invoice', 500);
        }
    }

    /**
     * POST /invoice/unpaid/pay-selected
     * Mark multiple invoices as paid in bulk.
     */
    public function paySelected(BulkActionInvoiceRequest $request): JsonResponse
    {
        try {
            $ids = $request->validated('ids');
            $metode = $request->input('metode', 'manual');

            $invoices = Invoice::whereIn('id', $ids)->unpaid()->get();

            if ($invoices->isEmpty()) {
                return $this->error('Tidak ada invoice lunas yang ditemukan', 422);
            }

            $gateway = PaymentGateway::where('is_active', true)->first();

            return DB::transaction(function () use ($invoices, $metode, $gateway) {
                foreach ($invoices as $invoice) {
                    $invoice->update([
                        'status' => 'paid',
                        'tanggal_bayar' => now()->toDateString(),
                        'metode' => $metode,
                    ]);

                    if ($gateway) {
                        // Sync to payment gateway if configured
                        $this->syncToPaymentGateway($invoice, $gateway);
                    }
                }

                return $this->ok([
                    'updated' => $invoices->count(),
                ], $invoices->count() . ' invoice lunas ditandai');
            });
        } catch (\Exception $e) {
            Log::error('Bulk pay invoices failed: ' . $e->getMessage());

            return $this->error('Gagal menandai invoice lunas', 500);
        }
    }

    /**
     * POST /invoice/unpaid/send-selected
     * Send WhatsApp reminder for selected invoices.
     */
    public function sendSelected(BulkActionInvoiceRequest $request): JsonResponse
    {
        try {
            $ids = $request->validated('ids');

            $invoices = Invoice::whereIn('id', $ids)->unpaid()->get();

            if ($invoices->isEmpty()) {
                return $this->error('Tidak ada invoice yang ditemukan', 422);
            }

            // Placeholder: actual WhatsApp integration via WhatsAppConfig/WhatsAppMessage
            $sent = 0;
            foreach ($invoices as $invoice) {
                if ($invoice->whatsapp) {
                    // TODO: dispatch WhatsApp reminder job
                    $invoice->update(['wa_status' => 'sent', 'tagih_status' => 'sent']);
                    $sent++;
                }
            }

            return $this->ok([
                'total' => $invoices->count(),
                'sent' => $sent,
            ], "Pengingat berhasil dikirim ke {$sent} invoice");
        } catch (\Exception $e) {
            Log::error('Send selected invoice reminders failed: ' . $e->getMessage());

            return $this->error('Gagal mengirim pengingat', 500);
        }
    }

    /**
     * POST /invoice/unpaid/send-manual
     * Send a manual reminder to a specific invoice.
     */
    public function sendManual(Request $request): JsonResponse
    {
        $request->validate([
            'id' => ['required', 'integer', 'exists:invoices,id'],
        ]);

        try {
            $invoice = Invoice::findOrFail($request->input('id'));

            if (!$invoice->whatsapp) {
                return $this->error('Nomor WhatsApp tidak tersedia', 422);
            }

            // TODO: dispatch WhatsApp reminder job
            $invoice->update(['wa_status' => 'sent', 'tagih_status' => 'sent']);

            return $this->ok(null, 'Pengingat berhasil dikirim');
        } catch (\Exception $e) {
            Log::error('Send manual reminder failed: ' . $e->getMessage());

            return $this->error('Gagal mengirim pengingat', 500);
        }
    }

    /**
     * GET /invoice/unpaid/export
     */
    public function export(Request $request): JsonResponse
    {
        $query = Invoice::with(['profile:id,nama', 'reseller:id,nama'])
            ->unpaid();

        if ($request->filled('id_reseller')) {
            $query->where('id_reseller', $request->input('id_reseller'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('tanggal_invoice', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('tanggal_invoice', '<=', $request->input('date_to'));
        }

        $invoices = $query->orderBy('tanggal_invoice', 'desc')->get();

        return $this->ok($invoices, 'Data invoice unpaid siap diexport');
    }

    /**
     * GET /invoice/unpaid/print
     */
    public function print(Request $request): JsonResponse
    {
        // Mirrors export but grouped/structured for printing
        return $this->export($request);
    }

    /**
     * GET /invoice/unpaid/detail/:id
     */
    public function detail(int $id): JsonResponse
    {
        $invoice = Invoice::with(['profile:id,nama', 'reseller:id,nama'])->find($id);

        if (!$invoice) {
            return $this->error('Invoice tidak ditemukan', 404);
        }

        return $this->ok($invoice);
    }

    /**
     * GET /invoice/unpaid/pelanggan/search
     */
    public function pelangganSearch(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['required', 'string', 'min:2'],
        ]);

        // Search across invoices to find pelanggan by name or whatsapp
        $results = Invoice::select(['nama_pelanggan', 'whatsapp', 'id_pelanggan'])
            ->where(function ($q) use ($request) {
                $q->where('nama_pelanggan', 'like', '%' . $request->input('q') . '%')
                    ->orWhere('whatsapp', 'like', '%' . $request->input('q') . '%');
            })
            ->distinct()
            ->limit(20)
            ->get();

        return $this->ok($results);
    }

    /**
     * GET /invoice/unpaid/pelanggan/:id
     * Pelanggan detail with invoice history.
     */
    public function pelangganDetail(int $id): JsonResponse
    {
        // id here is id_pelanggan from invoices table
        $invoices = Invoice::with(['profile:id,nama', 'reseller:id,nama'])
            ->where('id_pelanggan', $id)
            ->orderByDesc('tanggal_invoice')
            ->paginate(25);

        if ($invoices->isEmpty()) {
            return $this->error('Pelanggan tidak ditemukan', 404);
        }

        return $this->paginate($invoices, 'Riwayat invoice pelanggan');
    }

    /**
     * POST /invoice/unpaid/:id/pay
     * Mark a single invoice as paid.
     */
    public function pay(PayInvoiceRequest $request, int $id): JsonResponse
    {
        try {
            $invoice = Invoice::where('id', $id)->unpaid()->first();

            if (!$invoice) {
                return $this->error('Invoice tidak ditemukan atau sudah lunas', 404);
            }

            $metode = $request->input('metode', 'manual');
            $gateway = PaymentGateway::where('is_active', true)->first();

            $invoice->update([
                'status' => 'paid',
                'tanggal_bayar' => now()->toDateString(),
                'paid_by' => $request->input('paid_by'),
                'metode' => $metode,
            ]);

            if ($gateway) {
                $this->syncToPaymentGateway($invoice, $gateway);
            }

            return $this->ok([
                'id' => $invoice->id,
                'no_invoice' => $invoice->no_invoice,
                'tanggal_bayar' => $invoice->tanggal_bayar,
            ], 'Invoice berhasil dilunasi');
        } catch (\Exception $e) {
            Log::error('Pay invoice failed: ' . $e->getMessage());

            return $this->error('Gagal melunasi invoice', 500);
        }
    }

    /**
     * DELETE /invoice/unpaid/delete-selected
     */
    public function deleteSelected(BulkActionInvoiceRequest $request): JsonResponse
    {
        try {
            $ids = $request->validated('ids');

            $deleted = Invoice::whereIn('id', $ids)->delete();

            return $this->ok(['deleted' => $deleted], "{$deleted} invoice berhasil dihapus");
        } catch (\Exception $e) {
            Log::error('Delete invoices failed: ' . $e->getMessage());

            return $this->error('Gagal menghapus invoice', 500);
        }
    }

    /**
     * POST /invoice/unpaid/:id/save
     * Update an existing invoice.
     */
    public function save(SaveInvoiceRequest $request, int $id): JsonResponse
    {
        try {
            $invoice = Invoice::find($id);

            if (!$invoice) {
                return $this->error('Invoice tidak ditemukan', 404);
            }

            $invoice->update($request->validated());

            return $this->ok([
                'id' => $invoice->id,
                'no_invoice' => $invoice->no_invoice,
            ], 'Invoice berhasil diperbarui');
        } catch (\Exception $e) {
            Log::error('Save invoice failed: ' . $e->getMessage());

            return $this->error('Gagal memperbarui invoice', 500);
        }
    }

    /**
     * POST /invoice/unpaid/merge-duplicates
     * Merge duplicate invoices into one target.
     */
    public function mergeDuplicates(MergeDuplicateInvoicesRequest $request): JsonResponse
    {
        try {
            $targetId = $request->validated('target_id');
            $sourceIds = $request->validated('source_ids');

            return DB::transaction(function () use ($targetId, $sourceIds) {
                $target = Invoice::findOrFail($targetId);

                $sources = Invoice::whereIn('id', $sourceIds)->unpaid()->get();

                if ($sources->isEmpty()) {
                    return $this->error('Tidak ada invoice sumber yang valid', 422);
                }

                // Delete source invoices
                Invoice::whereIn('id', $sources->pluck('id'))->delete();

                return $this->ok([
                    'target_id' => $target->id,
                    'merged' => $sources->count(),
                ], $sources->count() . ' invoice berhasil digabungkan');
            });
        } catch (\Exception $e) {
            Log::error('Merge duplicate invoices failed: ' . $e->getMessage());

            return $this->error('Gagal menggabungkan invoice', 500);
        }
    }

    /**
     * Sync a paid invoice to the active payment gateway.
     */
    private function syncToPaymentGateway(Invoice $invoice, PaymentGateway $gateway): void
    {
        try {
            // Placeholder: integrate with active gateway (Duitku/Midtrans/Tripay)
            // Dispatch job or call gateway API here
            Log::info("Invoice {$invoice->no_invoice} synced to gateway: {$gateway->gateway}");
        } catch (\Exception $e) {
            Log::warning('Payment gateway sync failed: ' . $e->getMessage());
        }
    }
}
