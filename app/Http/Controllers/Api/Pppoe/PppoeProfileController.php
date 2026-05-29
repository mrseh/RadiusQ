<?php

namespace App\Http\Controllers\Api\Pppoe;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\Pppoe\StorePppoeProfileRequest;
use App\Models\Profile;
use App\Services\RadiusSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PppoeProfileController extends BaseApiController
{
    public function __construct(
        protected RadiusSyncService $radiusSync
    ) {}

    /**
     * GET /pppoe/profile/ajax
     * Paginated list of PPPoE and Hotspot profiles (filter by tipe).
     */
    public function ajax(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('length', 25), 100);
        $page = (int) $request->get('start', 0) > 0
            ? (int) floor($request->get('start', 0) / $perPage) + 1
            : 1;

        $query = Profile::query()
            ->when($request->get('tipe'), fn($q) => $q->where('tipe', $request->get('tipe')))
            ->when($request->get('search'), function ($q, $search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('group_name', 'like', "%{$search}%");
            })
            ->when($request->get('status'), fn($q) => $q->where('status', $request->get('status')))
            ->orderBy('nama');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return $this->paginate($paginator);
    }

    /**
     * POST /pppoe/profile/store
     * Create or update a profile. On create, syncs to RADIUS.
     */
    public function store(StorePppoeProfileRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['status'] = $data['status'] ?? 'aktif';

        // Support update (id provided) vs create
        $isUpdate = $request->filled('id');

        if ($isUpdate) {
            $profile = Profile::find($request->get('id'));
            if (!$profile) {
                return $this->error('Profile tidak ditemukan', 404);
            }
            $profile->update($data);
        } else {
            // Set user_count default on create
            $data['user_count'] = 0;
            $profile = Profile::create($data);
        }

        // Sync to RADIUS
        $this->radiusSync->syncPPPoEProfile($profile);

        Log::info('PPPoE: Profile saved', [
            'id' => $profile->id,
            'nama' => $profile->nama,
            'tipe' => $profile->tipe,
            'action' => $isUpdate ? 'update' : 'create',
        ]);

        return $this->ok(
            $profile->fresh(),
            $isUpdate ? 'Profile berhasil diperbarui' : 'Profile berhasil dibuat'
        );
    }

    /**
     * GET /pppoe/profile
     * Redirects to profile ajax endpoint.
     */
    public function index(): JsonResponse
    {
        return $this->ok(['redirect' => route('pppoe.profile.ajax')], '', 301);
    }

    /**
     * PUT /pppoe/profile/:id/toggle-status
     * Toggle profile between aktif and nonaktif.
     */
    public function toggleStatus(int $id): JsonResponse
    {
        $profile = Profile::find($id);
        if (!$profile) {
            return $this->error('Profile tidak ditemukan', 404);
        }

        $profile->status = $profile->status === 'aktif' ? 'nonaktif' : 'aktif';
        $profile->save();

        // Re-sync to RADIUS if disabling
        if ($profile->status === 'aktif') {
            $this->radiusSync->syncPPPoEProfile($profile);
        } else {
            $this->radiusSync->removeHotspotProfile($profile->nama);
        }

        Log::info('PPPoE: Profile status toggled', [
            'id' => $profile->id,
            'nama' => $profile->nama,
            'status' => $profile->status,
        ]);

        return $this->ok(['status' => $profile->status], 'Status profile berhasil diubah');
    }
}