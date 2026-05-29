<?php

namespace App\Http\Controllers\Api\Hotspot;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\HotspotSession;
use App\Models\HotspotUser;
use App\Services\RadiusSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HotspotSessionController extends BaseApiController
{
    public function __construct(
        private readonly RadiusSessionService $sessionService,
    ) {}

    public function ajax(Request $request): JsonResponse
    {
        $query = HotspotSession::with(['hotspotUser:id,username,nama,status'])
            ->where('start_time', '>', now()->subHours(24))
            ->whereNull('terminate_cause');

        if ($request->filled('nas')) {
            $query->where('nas', $request->input('nas'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        $length = (int) $request->input('length', 25);
        $start = (int) $request->input('start', 0);
        $page = ($start / $length) + 1;

        $paginator = $query->orderByDesc('start_time')->paginate($length, ['*'], 'page', $page);

        $data = collect($paginator->items())->map(fn($session) => [
            'id' => $session->id,
            'username' => $session->username,
            'ip_address' => $session->ip_address,
            'mac_address' => $session->mac_address,
            'nas' => $session->nas,
            'input_octets' => $session->input_octets,
            'output_octets' => $session->output_octets,
            'total_octets' => $session->input_octets + $session->output_octets,
            'session_time' => $session->session_time,
            'start_time' => $session->start_time?->toIso8601String(),
            'user' => $session->hotspotUser ? [
                'id' => $session->hotspotUser->id,
                'nama' => $session->hotspotUser->nama,
                'status' => $session->hotspotUser->status,
            ] : null,
        ]);

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => HotspotSession::where('start_time', '>', now()->subHours(24))
                ->whereNull('terminate_cause')
                ->count(),
            'recordsFiltered' => $paginator->total(),
            'data' => $data,
        ]);
    }

    public function clearSession(Request $request): JsonResponse
    {
        $request->validate([
            'username' => ['required', 'string'],
        ]);

        try {
            $this->sessionService->syncActiveSessions();

            $cleared = HotspotSession::where('username', $request->input('username'))
                ->whereNotNull('terminate_cause')
                ->delete();

            return $this->ok(['cleared' => $cleared], 'Session berhasil dibersihkan');
        } catch (\Exception $e) {
            Log::error('Clear session failed: ' . $e->getMessage());
            return $this->error('Gagal membersihkan session: ' . $e->getMessage(), 500);
        }
    }

    public function kickSelected(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer'],
        ]);

        try {
            $sessions = HotspotSession::whereIn('id', $request->input('ids'))->get();

            foreach ($sessions as $session) {
                $user = HotspotUser::where('username', $session->username)->first();
                if ($user) {
                    $session->update([
                        'terminate_cause' => 'User-Request',
                    ]);
                }
            }

            return $this->ok(['kicked' => $sessions->count()], "Berhasil logout {$sessions->count()} session");
        } catch (\Exception $e) {
            Log::error('Kick selected sessions failed: ' . $e->getMessage());
            return $this->error('Gagal kick session: ' . $e->getMessage(), 500);
        }
    }

    public function deleteSelected(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer'],
        ]);

        try {
            $deleted = HotspotSession::whereIn('id', $request->input('ids'))->delete();

            return $this->ok(['deleted' => $deleted], "Berhasil menghapus {$deleted} session record");
        } catch (\Exception $e) {
            Log::error('Delete selected sessions failed: ' . $e->getMessage());
            return $this->error('Gagal menghapus session: ' . $e->getMessage(), 500);
        }
    }
}
