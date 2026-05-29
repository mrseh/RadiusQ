<?php

namespace App\Http\Controllers\Api\Pppoe;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\Pppoe\BulkActionRequest;
use App\Models\PppoeUser;
use App\Models\PppoeSession;
use App\Services\RadiusSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PppoeOnlineController extends BaseApiController
{
    public function __construct(
        protected RadiusSyncService $radiusSync
    ) {}

    /**
     * GET /pppoe/online/ajax
     * Returns currently active PPPoE sessions (status = online/1).
     */
    public function ajax(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('length', 25), 100);
        $page = (int) $request->get('start', 0) > 0
            ? (int) floor($request->get('start', 0) / $perPage) + 1
            : 1;

        $query = PppoeSession::query()
            ->active()
            ->with(['pppoeUser:id,username,nama,whatsapp,id_profile,status'])
            ->when($request->get('search'), function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('username', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%")
                        ->orWhere('mac_address', 'like', "%{$search}%");
                });
            })
            ->when($request->get('nas'), fn($q) => $q->where('nas', $request->get('nas')))
            ->orderBy('start_time', 'desc');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return $this->paginate($paginator);
    }

    /**
     * DELETE /pppoe/session/clear-session
     * Clear/kick a specific active session.
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

        // Remove from RADIUS (kick the user)
        $this->radiusSync->suspendUser($session->username, true);

        // Mark session as cleared
        $session->update([
            'status' => 0,
            'stop_time' => now(),
        ]);

        Log::info('PPPoE: Cleared session', [
            'session_id' => $session->id,
            'username' => $session->username,
        ]);

        return $this->ok(null, 'Session berhasil di-clear');
    }

    /**
     * POST /pppoe/session/kick-selected
     * Kick selected users offline (add to RADIUS blacklist / disable session).
     */
    public function kickSelected(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:pppoe_sessions,id'],
        ]);

        $sessions = PppoeSession::whereIn('id', $request->get('ids'))->get();

        foreach ($sessions as $session) {
            $this->radiusSync->suspendUser($session->username, true);

            $session->update([
                'status' => 0,
                'stop_time' => now(),
            ]);
        }

        Log::info('PPPoE: Kicked selected sessions', ['ids' => $request->get('ids')]);

        return $this->ok(['kicked' => $sessions->count()], 'User berhasil di-kick');
    }

    /**
     * DELETE /pppoe/session/delete-selected
     * Delete selected session records (not the user accounts).
     */
    public function deleteSelected(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:pppoe_sessions,id'],
        ]);

        $deleted = PppoeSession::whereIn('id', $request->get('ids'))->delete();

        Log::info('PPPoE: Deleted session records', ['ids' => $request->get('ids')]);

        return $this->ok(['deleted' => $deleted], 'Session record berhasil dihapus');
    }

    /**
     * GET /pppoe/session/detail/:id
     * Get session detail with user and NAS info.
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

        // Fetch additional radacct data for this session
        $radacct = DB::table('radacct')
            ->where('username', $session->username)
            ->where('acctstarttime', '>=', $session->start_time)
            ->orderBy('acctstarttime', 'desc')
            ->limit(1)
            ->first();

        return $this->ok([
            'session' => $session,
            'radacct' => $radacct,
        ]);
    }
}