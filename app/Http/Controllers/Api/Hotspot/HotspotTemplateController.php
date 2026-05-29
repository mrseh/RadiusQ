<?php

namespace App\Http\Controllers\Api\Hotspot;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Hotspot\StoreHotspotTemplateRequest;
use App\Models\HotspotTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HotspotTemplateController extends BaseApiController
{
    public function ajax(Request $request): JsonResponse
    {
        $query = HotspotTemplate::query();

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

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => HotspotTemplate::count(),
            'recordsFiltered' => $paginator->total(),
            'data' => $paginator->items(),
        ]);
    }

    public function store(StoreHotspotTemplateRequest $request): JsonResponse
    {
        try {
            $template = HotspotTemplate::create($request->validated());

            return $this->ok([
                'id' => $template->id,
                'name' => $template->name,
            ], 'Template hotspot berhasil dibuat');
        } catch (\Exception $e) {
            Log::error('Store hotspot template failed: ' . $e->getMessage());
            return $this->error('Gagal membuat template: ' . $e->getMessage(), 500);
        }
    }

    public function show(Request $request): JsonResponse
    {
        $query = HotspotTemplate::query();

        if ($request->filled('status')) {
            $query->where('status', 'active');
        }

        $templates = $query->orderBy('name')->get(['id', 'name', 'content', 'variables', 'status']);

        return $this->ok($templates);
    }
}
