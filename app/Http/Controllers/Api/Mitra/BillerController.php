<?php

namespace App\Http\Controllers\Api\Mitra;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Mitra\StoreBillerRequest;
use App\Models\Billers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BillerController extends BaseApiController
{
    /**
     * GET /mitra/biller/ajax
     */
    public function ajax(Request $request): JsonResponse
    {
        $query = Billers::with('reseller:id,nama');

        if ($request->filled('id_reseller')) {
            $query->where('id_reseller', $request->input('id_reseller'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('kontak', 'like', "%{$search}%");
            });
        }

        $length = (int) $request->input('length', 25);
        $start = (int) $request->input('start', 0);
        $page = ($start / $length) + 1;

        $paginator = $query->orderByDesc('id')->paginate($length, ['*'], 'page', $page);

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => Billers::count(),
            'recordsFiltered' => $paginator->total(),
            'data' => $paginator->items(),
        ]);
    }

    /**
     * POST /mitra/biller/store
     */
    public function store(StoreBillerRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $data['status'] = $data['status'] ?? 'aktif';

            $biller = Billers::create($data);

            return $this->ok([
                'id' => $biller->id,
                'nama' => $biller->nama,
            ], 'Biller berhasil dibuat');
        } catch (\Exception $e) {
            Log::error('Biller store failed: ' . $e->getMessage());

            return $this->error('Gagal membuat biller', 500);
        }
    }

    /**
     * GET /mitra/biller
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 25), 100);

        $query = Billers::with('reseller:id,nama');

        if ($request->filled('id_reseller')) {
            $query->where('id_reseller', $request->input('id_reseller'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $paginator = $query->orderByDesc('id')->paginate($perPage);

        return $this->paginate($paginator, 'Daftar biller');
    }
}