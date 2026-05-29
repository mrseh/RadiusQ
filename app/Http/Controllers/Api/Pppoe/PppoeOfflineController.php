<?php

namespace App\Http\Controllers\Api\Pppoe;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\PppoeSession;
use App\Services\RadiusSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PppoeOfflineController extends BaseApiController
{
    public function __construct(
        protected RadiusSyncService $radiusSync
    ) {}

    /**
     * GET /pppoe/offline/ajax
     * Returns inactive/recently-stopped PPPoE sessions (status = offline/0).
     */
    public function ajax(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('length', 25), 100);
        $page = (int) $request->get('start', 0) > 0
            ? (int) floor($request->get('start', 0) / $perPage) + 1
            : 1;

        $query = PppoeSession::query()
            ->where('status', 0)
            ->with(['pppoeUser:id,username,nama,whatsapp,id_profile,status'])
            ->when($request->get('search'), function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('username', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%")
                        ->orWhere('mac_address', 'like', "%{$search}%");
                });
            })
            ->when($request->get('nas'), fn($q) => $q->where('nas', $request->get('nas')))
            ->when($request->get('date_from'), fn($q) => $q->where('start_time', '>=', $request->get('date_from')))
            ->when($request->get('date_to'), fn($q) => $q->where('start_time', '<=', $request->get('date_to')))
            ->orderBy('stop_time', 'desc');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return $this->paginate($paginator);
    }

    /**
     * DELETE /pppoe/session/clear-session
     * Clear/delete a specific offline session record.
     */
    public function clearSession(Request $request): JsonResponse
    {
        $request->validate([
            'id' => ['required', 'integer', 'exists:pppoe_sessions,id'],
        ]);

        $session = PppoeSession::find($request->get('id'));

        if (!$session) {
            return $this->error('Session tidak ditemukan', 404);
        }

        $session->delete();

        Log::info('PPPoE: Cleared offline session record', [
            'session_id' => $session->id,
            'username' => $session->username,
        ]);

        return $this->ok(null, 'Session offline berhasil dihapus');
    }

    /**
     * POST /pppoe/session/kick-selected
     * Kick selected (offline) users — no-op since already offline,
     * but still log and respond.
     */
    public function kickSelected(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:pppoe_sessions,id'],
        ]);

        // Sessions are offline — nothing to kick.
        // Still notify caller with count.
        Log::info('PPPoE: Kick requested for offline sessions (no-op)', [
            'ids' => $request->get('ids'),
        ]);

        return $this->ok(['kicked' => 0, 'note' => 'User sudah offline'], 'User sudah offline');
    }

    /**
     * DELETE /pppoe/session/delete-selected
     * Delete selected offline session records.
     */
    public function deleteSelected(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:pppoe_sessions,id'],
        ]);

        $deleted = PppoeSession::whereIn('id', $request->get('ids'))->delete();

        Log::info('PPPoE: Deleted offline session records', ['ids' => $request->get('ids')]);

        return $this->ok(['deleted' => $deleted], 'Session record berhasil dihapus');
    }

    /**
     * GET /pppoe/session/detail/:id
     * Get offline session detail with user and NAS info.
     */
    public function detail(int $id): JsonResponse
    {
        $session = PppoeSession::with([
            'pppoeUser.profile',
            'pppoeUser.pop',
            'pppoeUser.reseller',
        ])->find($id);

        if (!$session) {
            return $this->error('Session tidak ditemukan', 404);
        }

        // Fetch radacct history for this session
        $radacct = DB::table('radacct')
            ->where('username', $session->username)
            ->where('acctstarttime', '>=', $session->start_time)
            ->orderBy('acctstarttime', 'desc')
            ->limit(5)
            ->get();

        return $this->ok([
            'session' => $session,
            'radacct_history' => $radacct,
        ]);
    }
}