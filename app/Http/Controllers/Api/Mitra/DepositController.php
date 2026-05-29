<?php

namespace App\Http\Controllers\Api\Mitra;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Mitra\StoreDepositRequest;
use App\Models\Deposit;
use App\Models\Reseller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DepositController extends BaseApiController
{
    /**
     * GET /mitra/deposit/ajax
     */
    public function ajax(Request $request): JsonResponse
    {
        $query = Deposit::with('reseller:id,nama');

        if ($request->filled('id_reseller')) {
            $query->where('id_reseller', $request->input('id_reseller'));
        }

        if ($request->filled('tipe')) {
            $query->where('tipe', $request->input('tipe'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('keterangan', 'like', "%{$search}%")
                    ->orWhere('paid_by', 'like', "%{$search}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $length = (int) $request->input('length', 25);
        $start = (int) $request->input('start', 0);
        $page = ($start / $length) + 1;

        $paginator = $query->orderByDesc('id')->paginate($length, ['*'], 'page', $page);

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => Deposit::count(),
            'recordsFiltered' => $paginator->total(),
            'data' => $paginator->items(),
        ]);
    }

    /**
     * POST /mitra/deposit/store
     * Creates a deposit and updates the reseller's saldo.
     * Debit = subtracts from saldo; Kredit = adds to saldo.
     */
    public function store(StoreDepositRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            return DB::transaction(function () use ($data) {
                $reseller = Reseller::lockForUpdate()->findOrFail($data['id_reseller']);

                $deposit = Deposit::create([
                    'id_reseller' => $data['id_reseller'],
                    'tipe' => $data['tipe'],
                    'jumlah' => $data['jumlah'],
                    'keterangan' => $data['keterangan'] ?? null,
                ]);

                // Update reseller saldo
                if ($data['tipe'] === 'kredit') {
                    $reseller->saldo = bcadd((string) $reseller->saldo, (string) $data['jumlah'], 2);
                } else {
                    $reseller->saldo = bcsub((string) $reseller->saldo, (string) $data['jumlah'], 2);
                }
                $reseller->save();

                return $this->ok([
                    'id' => $deposit->id,
                    'jumlah' => $deposit->jumlah,
                    'tipe' => $deposit->tipe,
                    'saldo_terbaru' => (float) $reseller->saldo,
                ], 'Deposit berhasil ditambahkan');
            });
        } catch (\Exception $e) {
            Log::error('Deposit store failed: ' . $e->getMessage());

            return $this->error('Gagal menambahkan deposit', 500);
        }
    }

    /**
     * GET /mitra/deposit
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 25), 100);

        $query = Deposit::with('reseller:id,nama');

        if ($request->filled('id_reseller')) {
            $query->where('id_reseller', $request->input('id_reseller'));
        }

        if ($request->filled('tipe')) {
            $query->where('tipe', $request->input('tipe'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $paginator = $query->orderByDesc('id')->paginate($perPage);

        return $this->paginate($paginator, 'Daftar deposit');
    }
}