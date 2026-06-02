<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\HotspotUser;
use App\Models\Invoice;
use App\Models\Mikrotik;
use App\Models\PppoeUser;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DashboardController extends BaseApiController
{
    public function stats(): JsonResponse
    {
        $stats = [
            'total_pppoe_users' => PppoeUser::count(),
            'total_hotspot_users' => HotspotUser::count(),
            'total_invoices' => Invoice::count(),
            'total_admins' => User::count(),
            'total_nas' => Mikrotik::count(),
            'active_pppoe' => PppoeUser::where('session_status', 'active')->count(),
            'active_hotspot' => HotspotUser::where('status', 'active')->count(),
            'unpaid_invoices' => Invoice::where('status', 'unpaid')->count(),
            'paid_invoices' => Invoice::where('status', 'paid')->count(),
        ];

        return $this->ok($stats);
    }
}
