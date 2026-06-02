<?php

namespace App\Http\Controllers\Api\Payment;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Payment\GetPaymentGatewaySettingRequest;
use App\Http\Requests\Payment\WithdrawAjaxRequest;
use App\Http\Requests\Payment\WithdrawConfirmRequest;
use App\Http\Requests\Payment\WithdrawRequestOtpRequest;
use App\Models\MootaConfig;
use App\Models\MootaTransaction;
use App\Models\PaymentGateway;
use App\Models\PaymentGatewayTransaction;
use App\Models\PaymentGatewayWithdraw;
use App\Models\Reseller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PaymentGatewayController extends BaseApiController
{
    public function ajax(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('length', 25), 100);
        $page = (int) $request->get('start', 0) > 0
            ? (int) floor($request->get('start', 0) / $perPage) + 1
            : 1;

        $query = PaymentGateway::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->where('gateway', 'like', '%'.$request->get('search').'%');
            })
            ->when($request->filled('gateway'), function ($q) use ($request) {
                $q->where('gateway', $request->get('gateway'));
            })
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')));

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $items = collect($paginator->items())->map(function ($gateway) {
            $stats = PaymentGatewayTransaction::where('gateway_id', $gateway->id)
                ->selectRaw('COUNT(*) as total_transaksi,
                    SUM(CASE WHEN status = \'success\' THEN gross_amount ELSE 0 END) as total_terima,
                    SUM(CASE WHEN status = \'success\' THEN 1 ELSE 0 END) as transaksi_berhasil')
                ->first();

            return [
                'id' => $gateway->id,
                'gateway' => $gateway->gateway,
                'credential_source' => $gateway->credential_source,
                'is_active' => $gateway->is_active,
                'admin_fee' => (float) $gateway->admin_fee,
                'total_transaksi' => (int) ($stats->total_transaksi ?? 0),
                'total_terima' => (float) ($stats->total_terima ?? 0),
                'transaksi_berhasil' => (int) ($stats->transaksi_berhasil ?? 0),
            ];
        });

        return $this->datatableResponse($items, $paginator->total(), $paginator->total());
    }

    public function setting(GetPaymentGatewaySettingRequest $getRequest, Request $request): JsonResponse
    {
        if ($request->isMethod('get') || ! $request->isMethod('post')) {
            if ($getRequest->filled('id')) {
                $gateway = PaymentGateway::find($getRequest->input('id'));
                if (! $gateway) {
                    return $this->error('Payment gateway tidak ditemukan.', 404);
                }

                return $this->ok($gateway, 'Gateway settings loaded.');
            }

            return $this->ok(PaymentGateway::all(), 'All gateway settings loaded.');
        }

        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:payment_gateways,id'],
            'gateway' => ['required', 'string', 'in:midtrans,duitku,tripay'],
            'credential_source' => ['nullable', 'string', 'in:default,mandiri'],
            'admin_fee' => ['nullable', 'numeric', 'min:0'],
            'duitku_admin_charge_to' => ['nullable', 'string', 'in:customer,merchant'],
            'duitku_merchant_code' => ['nullable', 'string', 'max:100'],
            'duitku_api_key' => ['nullable', 'string', 'max:255'],
            'midtrans_merchant_id' => ['nullable', 'string', 'max:100'],
            'midtrans_client_key' => ['nullable', 'string', 'max:255'],
            'midtrans_server_key' => ['nullable', 'string', 'max:255'],
            'tripay_merchant_code' => ['nullable', 'string', 'max:100'],
            'tripay_api_key' => ['nullable', 'string', 'max:255'],
            'tripay_private_key' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (! empty($data['id'])) {
            $gateway = PaymentGateway::find($data['id']);
            if (! $gateway) {
                return $this->error('Payment gateway tidak ditemukan.', 404);
            }
            $gateway->update($data);

            return $this->ok($gateway, 'Gateway settings updated.');
        }

        $gateway = PaymentGateway::create($data);

        return $this->ok($gateway, 'Gateway settings saved.', 201);
    }

    public function transactionAjax(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('length', 25), 100);
        $page = (int) $request->get('start', 0) > 0
            ? (int) floor($request->get('start', 0) / $perPage) + 1
            : 1;

        $query = PaymentGatewayTransaction::with(['gateway:id,gateway'])
            ->when($request->filled('gateway_id'), fn ($q) => $q->where('gateway_id', $request->input('gateway_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('start_date'), fn ($q) => $q->whereDate('tanggal', '>=', $request->input('start_date')))
            ->when($request->filled('end_date'), fn ($q) => $q->whereDate('tanggal', '<=', $request->input('end_date')))
            ->orderByDesc('tanggal');

        $total = (clone $query)->count();
        $items = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        $data = $items->map(fn ($t) => [
            'id' => $t->id,
            'gateway' => $t->gateway?->gateway,
            'tanggal' => $t->tanggal?->toISOString(),
            'ref_id' => $t->ref_id,
            'metode' => $t->metode,
            'kategori' => $t->kategori,
            'deskripsi' => $t->deskripsi,
            'gross_amount' => (float) $t->gross_amount,
            'fee_amount' => (float) $t->fee_amount,
            'debit' => (float) $t->debit,
            'kredit' => (float) $t->kredit,
            'saldo' => (float) $t->saldo,
            'status' => $t->status,
        ]);

        return $this->datatableResponse($data, $total, $total);
    }

    public function transactionBalance(Request $request): JsonResponse
    {
        $gatewayId = $request->input('gateway_id');

        $query = PaymentGatewayTransaction::where('status', 'success');
        if ($gatewayId) {
            $query->where('gateway_id', $gatewayId);
        }

        $totalDebit = (clone $query)->sum('debit');
        $totalKredit = (clone $query)->sum('kredit');
        $lastSaldo = (clone $query)->latest('tanggal')->value('saldo') ?? 0;

        return $this->ok([
            'total_debit' => (float) $totalDebit,
            'total_kredit' => (float) $totalKredit,
            'saldo_terakhir' => (float) $lastSaldo,
        ]);
    }

    public function withdrawAjax(WithdrawAjaxRequest $request): JsonResponse
    {
        $perPage = min((int) $request->get('length', 25), 100);
        $page = (int) $request->get('start', 0) > 0
            ? (int) floor($request->get('start', 0) / $perPage) + 1
            : 1;

        $query = PaymentGatewayWithdraw::with(['gateway:id,gateway'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('gateway_id'), fn ($q) => $q->where('gateway_id', $request->input('gateway_id')))
            ->when($request->filled('start_date'), fn ($q) => $q->whereDate('tanggal', '>=', $request->input('start_date')))
            ->when($request->filled('end_date'), fn ($q) => $q->whereDate('tanggal', '<=', $request->input('end_date')))
            ->orderByDesc('tanggal');

        $total = (clone $query)->count();
        $items = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        $data = $items->map(fn ($w) => [
            'id' => $w->id,
            'gateway' => $w->gateway?->gateway,
            'tanggal' => $w->tanggal?->toISOString(),
            'withdraw_id' => $w->withdraw_id,
            'nama_bank' => $w->nama_bank,
            'no_rekening' => $w->no_rekening,
            'atas_nama' => $w->atas_nama,
            'amount' => (float) $w->amount,
            'fee' => (float) $w->fee,
            'status' => $w->status,
        ]);

        return $this->datatableResponse($data, $total, $total);
    }

    public function withdrawAvailable(Request $request): JsonResponse
    {
        $user = $request->user();
        $reseller = Reseller::where('id_user', $user->id)->first();
        if (! $reseller) {
            return $this->error('Reseller tidak ditemukan.', 404);
        }

        $totalMasuk = PaymentGatewayTransaction::whereHas('gateway', fn ($q) => $q->where('is_active', true))
            ->where('status', 'success')
            ->where('kredit', '>', 0)
            ->sum('kredit');

        $totalTarik = PaymentGatewayWithdraw::whereIn('status', ['success', 'approved'])
            ->where('amount', '>', 0)
            ->sum('amount');

        return $this->ok([
            'saldo_masuk' => (float) $totalMasuk,
            'sudah_tarik' => (float) $totalTarik,
            'saldo_tersedia' => max(0, (float) $totalMasuk - (float) $totalTarik),
        ]);
    }

    public function withdrawRequestOtp(WithdrawRequestOtpRequest $request): JsonResponse
    {
        $user = $request->user();
        $jumlah = $request->input('jumlah');
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $cacheKey = "withdraw_otp:{$user->id}";
        Cache::put($cacheKey, [
            'otp' => $otp,
            'jumlah' => $jumlah,
            'gateway_id' => $request->input('gateway_id'),
            'expires_at' => now()->addMinutes(5)->toISOString(),
        ], 300);

        return $this->ok([
            'message' => 'Kode OTP telah dikirim.',
            'otp_sent_to' => $user->whatsapp
                ? substr($user->whatsapp, 0, 4).'****'.substr($user->whatsapp, -3)
                : substr($user->email, 0, 3).'***@'.explode('@', $user->email)[1],
            'expires_in_seconds' => 300,
            '_demo_otp' => $otp,
        ]);
    }

    public function withdrawConfirm(WithdrawConfirmRequest $request): JsonResponse
    {
        $user = $request->user();
        $cacheKey = "withdraw_otp:{$user->id}";
        $cached = Cache::get($cacheKey);

        if (! $cached) {
            return $this->error('Kode OTP sudah kadaluarsa. Silakan minta OTP baru.', 422);
        }

        if ($cached['otp'] !== $request->input('otp')) {
            return $this->error('Kode OTP tidak valid.', 422);
        }

        if ((float) $cached['jumlah'] !== (float) $request->input('jumlah')) {
            return $this->error('Jumlah penarikan tidak sesuai.', 422);
        }

        Cache::forget($cacheKey);

        $fee = round((float) $request->input('jumlah') * 0.001, 2);

        $withdraw = PaymentGatewayWithdraw::create([
            'gateway_id' => $cached['gateway_id'],
            'tanggal' => now(),
            'withdraw_id' => 'WD-'.strtoupper(Str::random(10)),
            'nama_bank' => $request->input('nama_bank', 'Bank Transfer'),
            'no_rekening' => $request->input('no_rekening', ''),
            'atas_nama' => $request->input('atas_nama', $user->name),
            'amount' => $request->input('jumlah'),
            'fee' => $fee,
            'status' => 'pending',
        ]);

        return $this->ok($withdraw, 'Permintaan penarikan berhasil diajukan.');
    }

    public function withdrawUpdateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'string', 'in:pending,success,failed,canceled'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        $withdraw = PaymentGatewayWithdraw::find($id);
        if (! $withdraw) {
            return $this->error('Withdraw tidak ditemukan.', 404);
        }

        $withdraw->update([
            'status' => $request->input('status'),
        ]);

        return $this->ok($withdraw, 'Status withdraw berhasil diperbarui.');
    }

    public function mootaConfig(): JsonResponse
    {
        $config = MootaConfig::first();

        return $this->ok($config ?? [
            'username' => null,
            'api_key' => null,
            'webhook_url' => null,
            'is_active' => false,
            'last_sync' => null,
        ]);
    }

    public function mootaConfigSave(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['nullable', 'string', 'max:100'],
            'api_key' => ['nullable', 'string', 'max:255'],
            'webhook_url' => ['nullable', 'url', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $config = MootaConfig::first();
        if ($config) {
            $config->update($data);
        } else {
            $config = MootaConfig::create($data);
        }

        return $this->ok($config, 'Konfigurasi Moota berhasil disimpan.');
    }

    public function mootaSync(Request $request): JsonResponse
    {
        $config = MootaConfig::where('is_active', true)->first();
        if (! $config) {
            return $this->error('Konfigurasi Moota belum aktif.', 422);
        }

        // Demo: simulate sync by updating last_sync timestamp
        $config->update(['last_sync' => now()]);

        $count = MootaTransaction::where('gateway_id', $config->id)
            ->whereDate('tanggal', '>=', now()->subDays(1))
            ->count();

        return $this->ok([
            'message' => 'Sinkronisasi berhasil.',
            'last_sync' => $config->last_sync->toISOString(),
            'new_transactions' => $count,
        ]);
    }

    public function mootaTransactions(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('length', 25), 100);
        $page = (int) $request->get('start', 0) > 0
            ? (int) floor($request->get('start', 0) / $perPage) + 1
            : 1;

        $query = MootaTransaction::with(['gateway:id,gateway'])
            ->when($request->filled('gateway_id'), fn ($q) => $q->where('gateway_id', $request->input('gateway_id')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->when($request->filled('start_date'), fn ($q) => $q->whereDate('tanggal', '>=', $request->input('start_date')))
            ->when($request->filled('end_date'), fn ($q) => $q->whereDate('tanggal', '<=', $request->input('end_date')))
            ->orderByDesc('tanggal');

        $total = (clone $query)->count();
        $items = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        $data = $items->map(fn ($t) => [
            'id' => $t->id,
            'gateway' => $t->gateway?->gateway,
            'tanggal' => $t->tanggal?->toISOString(),
            'description' => $t->description,
            'amount' => (float) $t->amount,
            'type' => $t->type,
            'akun' => $t->akun,
        ]);

        return $this->datatableResponse($data, $total, $total);
    }
}
