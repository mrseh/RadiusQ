<?php

namespace App\Http\Controllers\Api\Hotspot;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Hotspot\StoreHotspotProfileRequest;
use App\Models\HotspotProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HotspotProfileController extends BaseApiController
{
    public function ajax(Request $request): JsonResponse
    {
        $query = HotspotProfile::query();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->input('search') . '%');
        }

        $length = (int) $request->input('length', 25);
        $start = (int) $request->input('start', 0);
        $page = ($start / $length) + 1;

        $paginator = $query->orderBy('name')->paginate($length, ['*'], 'page', $page);

        $data = collect($paginator->items())->map(fn($profile) => [
            'id' => $profile->id,
            'name' => $profile->name,
            'rate_limit' => $profile->rate_limit,
            'valid_for' => $profile->valid_for,
            'shared_users' => $profile->shared_users,
            'price' => $profile->price,
            'note' => $profile->note,
            'status' => $profile->status,
            'user_count' => $profile->hotspotUsers()->count(),
        ]);

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => HotspotProfile::count(),
            'recordsFiltered' => $paginator->total(),
            'data' => $data,
        ]);
    }

    public function store(StoreHotspotProfileRequest $request): JsonResponse
    {
        try {
            $profile = HotspotProfile::create($request->validated());

            return $this->ok([
                'id' => $profile->id,
                'name' => $profile->name,
            ], 'Profil hotspot berhasil dibuat');
        } catch (\Exception $e) {
            Log::error('Store hotspot profile failed: ' . $e->getMessage());
            return $this->error('Gagal membuat profil: ' . $e->getMessage(), 500);
        }
    }

    public function show(Request $request): JsonResponse
    {
        $query = HotspotProfile::query();

        if ($request->filled('status')) {
            $query->where('status', 'active');
        }

        $profiles = $query->orderBy('name')->get(['id', 'name', 'rate_limit', 'price', 'status']);

        return $this->ok($profiles);
    }

    public function toggleStatus(Request $request, int $id): JsonResponse
    {
        $profile = HotspotProfile::findOrFail($id);

        $newStatus = $profile->status === 'active' ? 'inactive' : 'active';

        try {
            $profile->update(['status' => $newStatus]);

            return $this->ok([
                'id' => $profile->id,
                'status' => $newStatus,
            ], "Profil berhasil " . ($newStatus === 'active' ? 'diaktifkan' : 'dinonaktifkan'));
        } catch (\Exception $e) {
            Log::error('Toggle hotspot profile status failed: ' . $e->getMessage());
            return $this->error('Gagal mengubah status profil: ' . $e->getMessage(), 500);
        }
    }
}
