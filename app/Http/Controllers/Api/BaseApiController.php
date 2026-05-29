<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\Request;

abstract class BaseApiController extends Controller
{
    use ApiResponse;

    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    protected function parseDatatableParams(Request $request): array
    {
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $page = $length > 0 ? (int) floor($start / $length) + 1 : 1;

        return [
            'page' => $page,
            'per_page' => $length,
            'order_column' => $request->input('columns.' . (int) $request->input('order.0.column') . '.data', 'id'),
            'order_dir' => $request->input('order.0.dir', 'desc'),
            'search' => $request->input('search.value', ''),
        ];
    }

    protected function datatableResponse(array $data, int $recordsTotal, int $recordsFiltered, array $extra = []): \Illuminate\Http\JsonResponse
    {
        return response()->json(array_merge([
            'draw' => (int) $this->request->input('draw', 1),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ], $extra));
    }
}
