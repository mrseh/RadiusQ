<?php

namespace App\Http\Controllers\Api\Mitra;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Mitra\StoreResellerRequest;
use App\Models\Reseller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ResellerController extends BaseApiController
{
    /**
     * GET /mitra/reseller/ajax
     * DataTable server-side list with draw/length/start pagination.
     */
    public function ajax(Request $request): JsonResponse
    {
        $query = Reseller::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('whatsapp', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $length = (int) $request->input('length', 25);
        $start = (int) $request->input('start', 0);
        $page = ($start / $length) + 1;

        $paginator = $query->orderByDesc('id')->paginate($length, ['*'], 'page', $page);

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => Reseller::count(),
            'recordsFiltered' => $paginator->total(),
            'data' => $paginator->items(),
        ]);
    }

    /**
     * POST /mitra/reseller/store
     */
    public function store(StoreResellerRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $data['status'] = $data['status'] ?? 'aktif';
            $data['saldo'] = $data['saldo'] ?? 0;
            $data['limit_hutang'] = $data['limit_hutang'] ?? 0;

            $reseller = Reseller::create($data);

            return $this->ok([
                'id' => $reseller->id,
                'nama' => $reseller->nama,
                'username' => $reseller->username,
            ], 'Reseller berhasil dibuat');
        } catch (\Exception $e) {
            Log::error('Reseller store failed: ' . $e->getMessage());

            return $this->error('Gagal membuat reseller', 500);
        }
    }

    /**
     * GET /mitra/reseller
     * Basic listing (non-ajax page load).
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 25), 100);

        $query = Reseller::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $paginator = $query->orderByDesc('id')->paginate($perPage);

        return $this->paginate($paginator, 'Daftar reseller');
    }
}