<?php

namespace App\Http\Controllers\Api\Log;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Log\LogAjaxRequest;
use App\Http\Requests\Log\ClearLogsRequest;
use App\Models\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogController extends BaseApiController
{
    /**
     * GET /log/ajax
     * Activity logs with date filter and module filter.
     */
    public function ajax(LogAjaxRequest $request): JsonResponse
    {
        $perPage = min((int) $request->get('length', 25), 100);
        $page = (int) $request->get('start', 0) > 0
            ? (int) floor($request->get('start', 0) / $perPage) + 1
            : 1;

        $query = Log::with(['user:id,name,email'])
            ->when($request->filled('module'), fn($q) => $q->where('module', $request->input('module')))
            ->when($request->filled('action'), fn($q) => $q->where('action', $request->input('action')))
            ->when($request->filled('start_date'), fn($q) => $q->whereDate('created_at', '>=', $request->input('start_date')))
            ->when($request->filled('end_date'), fn($q) => $q->whereDate('created_at', '<=', $request->input('end_date')))
            ->when($request->get('search'), function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('deskripsi', 'like', "%{$search}%")
                        ->orWhere('module', 'like', "%{$search}%")
                        ->orWhere('action', 'like', "%{$search}%");
                });
            })
            ->orderBy('created_at', 'desc');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'status' => true,
            'message' => 'OK',
            'data' => collect($paginator->items())->map(fn($log) => [
                'id' => $log->id,
                'module' => $log->module,
                'action' => $log->action,
                'deskripsi' => $log->deskripsi,
                'reference_id' => $log->reference_id,
                'user' => $log->user?->name,
                'user_email' => $log->user?->email,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->toISOString(),
            ]),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * DELETE /log/clear-all
     * Clear all logs (with password confirmation).
     */
    public function clearAll(ClearLogsRequest $request): JsonResponse
    {
        $count = Log::count();
        Log::truncate();

        Log::create([
            'module' => 'Log',
            'action' => 'clear_all',
            'deskripsi' => "Menghapus semua log ({$count} entri) oleh user ID: {$request->user()->id}",
            'id_user' => $request->user()->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        return $this->ok(['deleted_count' => $count], "{$count} entri log berhasil dihapus.");
    }
}