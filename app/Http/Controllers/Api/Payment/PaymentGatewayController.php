<?php

namespace App\Http\Controllers\Api\Payment;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Payment\GetPaymentGatewaySettingRequest;
use App\Http\Requests\Payment\SavePaymentGatewaySettingRequest;
use App\Http\Requests\Payment\WithdrawAjaxRequest;
use App\Http\Requests\Payment\SetDefaultBankRequest;
use App\Http\Requests\Payment\WithdrawRequestOtpRequest;
use App\Http\Requests\Payment\WithdrawConfirmRequest;
use App\Models\PaymentGateway;
use App\Models\PaymentGatewayTransaction;
use App\Models\PaymentGatewayWithdraw;
use App\Models\Rekening;
use App\Models\Reseller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PaymentGatewayController extends BaseApiController
{
    /**
     * GET /payment-gateway/ajax
     * List payment gateways + aggregated transaction stats.
     */
    public function ajax(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('length', 25), 100);
        $page = (int) $request->get('start', 0) > 0
            ? (int) floor($request->get('start', 0) / $perPage) + 1
            : 1;

        $query = PaymentGateway::query()
            ->when($request->get('search'), function ($q, $search) {
                $q->where('nama', 'like', "%{$search}%");
            })
            ->when($request->get('tipe'), fn($q) => $q->where('tipe', $request->get('tipe')))
            ->when($request->get('is_active') !== null, fn($q) => $q->where('is_active', $request->get('is_active')));

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $items = collect($paginator->items())->map(function ($gateway) {
            $stats = PaymentGatewayTransaction::query()
                ->where('gateway_id', $gateway->id)
                ->selectRaw('
                    COUNT(*) as total_transaksi,
                    SUM(CASE WHEN status = \'paid\' THEN gross_amount ELSE 0 END) as total_terima,
                    SUM(CASE WHEN status = \'paid\' THEN 1 ELSE 0 END) as transaksi_berhasil
                ')
                ->first();

            return [
                'id' => $gateway->id,
                'nama' => $gateway->nama,
                'tipe' => $gateway->tipe,
                'is_active' => $gateway->is_active,
                'total_transaksi' => (int) ($stats->total_transaksi ?? 0),
                'total_terima' => (float) ($stats->total_terima ?? 0),
                'transaksi_berhasil' => (int) ($stats->transaksi_berhasil ?? 0),
            ];
        });

        return response()->json([
            'status' => true,
            'message' => 'OK',
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * GET|POST /payment-gateway/setting
     * GET: retrieve gateway settings (optionally filtered by id).
     * POST: save / update gateway settings.
     */
    public function setting(GetPaymentGatewaySettingRequest $getRequest, SavePaymentGatewaySettingRequest $postRequest): JsonResponse
    {
        // GET branch
        if ($getRequest->isMethod('get') || !$postRequest->isMethod('post')) {
            if ($getRequest->filled('id')) {
                $gateway = PaymentGateway::find($getRequest->input('id'));
                if (!$gateway) {
                    return $this->error('Payment gateway tidak ditemukan.', 404);
                }
                return $this->ok($gateway, 'Gateway settings loaded.');
            }

            $gateways = PaymentGateway::all();
            return $this->ok($gateways, 'All gateway settings loaded.');
        }

        // POST branch — save/update
        $data = $postRequest->validated();

        if (!empty($data['id'])) {
            $gateway = PaymentGateway::find($data['id']);
            if (!$gateway) {
                return $this->error('Payment gateway tidak ditemukan.', 404);
            }
            $gateway->update($data);
            return $this->ok($gateway, 'Gateway settings updated.');
        }

        $gateway = PaymentGateway::create($data);
        return $this->ok($gateway, 'Gateway settings saved.', 201);
    }

    /**
     * POST /payment-gateway/withdraw/ajax
     * Withdraw request list with optional status/id_reseller filter.
     */
    public function withdrawAjax(WithdrawAjaxRequest $request): JsonResponse
    {
        $perPage = min((int) $request->get('length', 25), 100);
        $page = (int) $request->get('start', 0) > 0
            ? (int) floor($request->get('start', 0) / $perPage) + 1
            : 1;

        $query = PaymentGatewayWithdraw::with(['gateway:id,nama'])
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('id_reseller'), fn($q) => $q->where('id_reseller', $request->input('id_reseller')))
            ->when($request->filled('start_date'), fn($q) => $q->whereDate('request_at', '>=', $request->input('start_date')))
            ->when($request->filled('end_date'), fn($q) => $q->whereDate('request_at', '<=', $request->input('end_date')))
            ->orderBy('request_at', 'desc');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'status' => true,
            'message' => 'OK',
            'data' => collect($paginator->items())->map(fn($w) => [
                'id' => $w->id,
                'id_reseller' => $w->id_reseller,
                'gateway' => $w->gateway?->nama,
                'jumlah' => (float) $w->amount,
                'fee' => (float) $w->fee,
                'status' => $w->status,
                'catatan' => $w->catatan,
                'request_at' => $w->request_at?->toISOString(),
                'processed_at' => $w->processed_at?->toISOString(),
            ]),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * POST /payment-gateway/withdraw/default-bank
     * Set reseller's default bank account.
     */
    public function withdrawDefaultBank(SetDefaultBankRequest $request): JsonResponse
    {
        $rekening = Rekening::find($request->input('id_rekening'));
        if (!$rekening) {
            return $this->error('Rekening tidak ditemukan.', 404);
        }

        DB::transaction(function () use ($rekening) {
            // Unset current default for this perusahaan
            Rekening::where('id_perusahaan', $rekening->id_perusahaan)
                ->where('is_default', true)
                ->update(['is_default' => false]);

            $rekening->is_default = true;
            $rekening->save();
        });

        return $this->ok($rekening, 'Rekening utama berhasil diubah.');
    }

    /**
     * POST /payment-gateway/withdraw/available
     * Get available balance for the authenticated reseller.
     */
    public function withdrawAvailable(Request $request): JsonResponse
    {
        $user = $request->user();

        $reseller = Reseller::where('id_user', $user->id)->first();
        if (!$reseller) {
            return $this->error('Reseller tidak ditemukan.', 404);
        }

        // Sum paid transactions minus withdrawals
        $totalMasuk = PaymentGatewayTransaction::where('id_reseller', $reseller->id)
            ->where('status', 'paid')
            ->sum('kredit');

        $totalTarik = PaymentGatewayWithdraw::where('id_reseller', $reseller->id)
            ->whereIn('status', ['approved', 'paid'])
            ->sum('amount');

        $available = max(0, $totalMasuk - $totalTarik);

        return $this->ok([
            'saldo_masuk' => (float) $totalMasuk,
            'sudah_tarik' => (float) $totalTarik,
            'saldo_tersedia' => $available,
        ]);
    }

    /**
     * POST /payment-gateway/withdraw/request-otp
     * Simulate OTP sending for withdrawal request.
     */
    public function withdrawRequestOtp(WithdrawRequestOtpRequest $request): JsonResponse
    {
        $user = $request->user();
        $jumlah = $request->input('jumlah');

        // Simulate OTP generation (6-digit random)
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store OTP in cache for 5 minutes
        $cacheKey = "withdraw_otp:{$user->id}";
        Cache::put($cacheKey, [
            'otp' => $otp,
            'jumlah' => $jumlah,
            'gateway_id' => $request->input('id_payment_gateway'),
            'expires_at' => now()->addMinutes(5)->toISOString(),
        ], 300);

        // In production this would send SMS/WhatsApp. Here we just return it for demo.
        return $this->ok([
            'message' => 'Kode OTP telah dikirim.',
            'otp_sent_to' => $user->whatsapp ?? substr($user->email, 0, 3) . '***@' . explode('@', $user->email)[1] ?? '***',
            'expires_in_seconds' => 300,
            // NOTE: Remove otp from response in production
            '_demo_otp' => $otp,
        ]);
    }

    /**
     * POST /payment-gateway/withdraw/confirm
     * Confirm withdrawal with OTP.
     */
    public function withdrawConfirm(WithdrawConfirmRequest $request): JsonResponse
    {
        $user = $request->user();
        $cacheKey = "withdraw_otp:{$user->id}";
        $cached = Cache::get($cacheKey);

        if (!$cached) {
            return $this->error('Kode OTP sudah kadaluarsa. Silakan minta OTP baru.', 422);
        }

        if ($cached['otp'] !== $request->input('otp')) {
            return $this->error('Kode OTP tidak valid.', 422);
        }

        if ((float) $cached['jumlah'] !== (float) $request->input('jumlah')) {
            return $this->error('Jumlah penarikan tidak sesuai.', 422);
        }

        // Clear OTP cache
        Cache::forget($cacheKey);

        // Create withdraw record
        $reseller = Reseller::where('id_user', $user->id)->first();
        $fee = $request->input('jumlah') * 0.001; // 0.1% fee

        $withdraw = PaymentGatewayWithdraw::create([
            'gateway_id' => $cached['gateway_id'],
            'tanggal' => now(),
            'withdraw_id' => 'WD-' . strtoupper(Str::random(10)),
            'nama_bank' => $request->input('nama_bank', 'Bank Transfer'),
            'no_rekening' => $request->input('no_rekening', ''),
            'atas_nama' => $request->input('atas_nama', $user->name),
            'amount' => $request->input('jumlah'),
            'fee' => $fee,
            'status' => 'pending',
            'id_reseller' => $reseller?->id,
        ]);

        return $this->ok($withdraw, 'Permintaan penarikan berhasil diajukan.');
    }
}