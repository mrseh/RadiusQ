<?php

namespace App\Http\Controllers\Api\Mitra;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Mitra\StoreOutletRequest;
use App\Models\Outlet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OutletController extends BaseApiController
{
    /**
     * GET /mitra/outlet/ajax
     */
    public function ajax(Request $request): JsonResponse
    {
        $query = Outlet::with([
            'reseller:id,nama',
            'biller:id,nama',
        ]);

        if ($request->filled('id_reseller')) {
            $query->where('id_reseller', $request->input('id_reseller'));
        }

        if ($request->filled('id_biller')) {
            $query->where('id_biller', $request->input('id_biller'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('kontak', 'like', "%{$search}%")
                    ->orWhere('alamat', 'like', "%{$search}%");
            });
        }

        $length = (int) $request->input('length', 25);
        $start = (int) $request->input('start', 0);
        $page = ($start / $length) + 1;

        $paginator = $query->orderByDesc('id')->paginate($length, ['*'], 'page', $page);

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => Outlet::count(),
            'recordsFiltered' => $paginator->total(),
            'data' => $paginator->items(),
        ]);
    }

    /**
     * POST /mitra/outlet/store
     */
    public function store(StoreOutletRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $data['status'] = $data['status'] ?? 'aktif';

            $outlet = Outlet::create($data);

            return $this->ok([
                'id' => $outlet->id,
                'nama' => $outlet->nama,
            ], 'Outlet berhasil dibuat');
        } catch (\Exception $e) {
            Log::error('Outlet store failed: ' . $e->getMessage());

            return $this->error('Gagal membuat outlet', 500);
        }
    }

    /**
     * GET /mitra/outlet
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 25), 100);

        $query = Outlet::with([
            'reseller:id,nama',
            'biller:id,nama',
        ]);

        if ($request->filled('id_reseller')) {
            $query->where('id_reseller', $request->input('id_reseller'));
        }

        if ($request->filled('id_biller')) {
            $query->where('id_biller', $request->input('id_biller'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $paginator = $query->orderByDesc('id')->paginate($perPage);

        return $this->paginate($paginator, 'Daftar outlet');
    }
}