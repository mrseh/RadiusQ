<?php

namespace App\Http\Controllers\Api\Map;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Map\GetUserMapDataRequest;
use App\Models\UserMapLocation;
use App\Models\Odp;
use App\Models\Profile;
use App\Models\PopArea;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MapController extends BaseApiController
{
    /**
     * GET /map/user/options
     * Map layer options: status filters, customer status, online status.
     */
    public function userOptions(Request $request): JsonResponse
    {
        $profiles = Profile::select('id', 'nama', 'tipe')->get();
        $pops = PopArea::select('id', 'nama')->get();

        return $this->ok([
            'layers' => [
                ['key' => 'all', 'label' => 'Semua'],
                ['key' => 'aktif', 'label' => 'Aktif'],
                ['key' => 'nonaktif', 'label' => 'Nonaktif'],
                ['key' => 'macet', 'label' => 'Macet'],
            ],
            'status_options' => [
                ['key' => 'aktif', 'label' => 'Aktif'],
                ['key' => 'nonaktif', 'label' => 'Nonaktif'],
                ['key' => 'macet', 'label' => 'Macet'],
            ],
            'online_status' => [
                ['key' => 'online', 'label' => 'Online'],
                ['key' => 'offline', 'label' => 'Offline'],
            ],
            'profiles' => $profiles,
            'pops' => $pops,
        ]);
    }

    /**
     * GET /map/user/data
     * All user locations as GeoJSON for Leaflet display.
     */
    public function userData(GetUserMapDataRequest $request): JsonResponse
    {
        $query = UserMapLocation::query()
            ->with(['profile:id,nama,tipe', 'pppoeUser:id,username'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('latitude', '!=', 0)
            ->where('longitude', '!=', 0);

        if ($request->filled('layer')) {
            $layer = $request->input('layer');
            if ($layer === 'aktif') {
                $query->where('customer_status', 'aktif');
            } elseif ($layer === 'nonaktif') {
                $query->where('customer_status', 'nonaktif');
            } elseif ($layer === 'macet') {
                $query->where('customer_status', 'macet');
            }
        }

        if ($request->filled('status')) {
            $query->where('customer_status', $request->input('status'));
        }

        if ($request->filled('online_status')) {
            $query->where('is_online', $request->input('online_status') === 'online');
        }

        if ($request->filled('id_pop')) {
            $query->where('pop', $request->input('id_pop'));
        }

        if ($request->filled('id_profile')) {
            $query->whereHas('pppoeUser', fn($q) => $q->where('id_profile', $request->input('id_profile')));
        }

        $locations = $query->get();
        $features = $locations->map(function ($loc) {
            $markerColor = match ($loc->customer_status ?? null) {
                'aktif' => ($loc->is_online ? '#22c55e' : '#f59e0b'),
                'nonaktif' => '#ef4444',
                'macet' => '#a855f7',
                default => '#6b7280',
            };

            return [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [(float) $loc->longitude, (float) $loc->latitude],
                ],
                'properties' => [
                    'id' => $loc->id,
                    'username' => $loc->username ?? $loc->id_pppoe_user,
                    'nama' => $loc->full_name,
                    'profile' => $loc->profile?->nama,
                    'profile_tipe' => $loc->profile?->tipe,
                    'status' => $loc->customer_status,
                    'is_online' => (bool) $loc->is_online,
                    'internet_status' => $loc->internet_status,
                    'wa' => $loc->wa,
                    'odp' => $loc->odp,
                    'pop' => $loc->pop,
                    'nas' => $loc->nas,
                    'address' => $loc->address,
                    'marker_color' => $markerColor,
                ],
            ];
        });

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }

    /**
     * GET /map/odp/options
     * ODP map layer options.
     */
    public function odpOptions(Request $request): JsonResponse
    {
        $pops = PopArea::select('id', 'nama')->get();

        return $this->ok([
            'layers' => [
                ['key' => 'all', 'label' => 'Semua ODP'],
                ['key' => 'available', 'label' => 'Tersedia'],
                ['key' => 'full', 'label' => 'Penuh'],
            ],
            'pops' => $pops,
        ]);
    }

    /**
     * GET /map/odp/data
     * All ODP locations with usage stats as GeoJSON.
     */
    public function odpData(Request $request): JsonResponse
    {
        $query = Odp::query()
            ->with(['pop:id,nama'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('latitude', '!=', 0)
            ->where('longitude', '!=', 0);

        if ($request->filled('layer')) {
            $layer = $request->input('layer');
            if ($layer === 'available') {
                $query->whereRaw('(jumlah_port - port_terpakai) > 0');
            } elseif ($layer === 'full') {
                $query->whereRaw('(jumlah_port - port_terpakai) <= 0');
            }
        }

        if ($request->filled('id_pop')) {
            $query->where('id_pop', $request->input('id_pop'));
        }

        $odps = $query->get();
        $features = $odps->map(function ($odp) {
            $usedPorts = $odp->port_terpakai ?? 0;
            $totalPorts = $odp->jumlah_port ?? 0;
            $remaining = max(0, $totalPorts - $usedPorts);
            $fillRatio = $totalPorts > 0 ? round(($usedPorts / $totalPorts) * 100, 1) : 0;

            $markerColor = match (true) {
                $fillRatio >= 90 => '#ef4444',  // red - full
                $fillRatio >= 70 => '#f59e0b',  // amber - almost full
                default => '#22c55e',              // green - available
            };

            return [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [(float) $odp->longitude, (float) $odp->latitude],
                ],
                'properties' => [
                    'id' => $odp->id,
                    'kode' => $odp->kode,
                    'nama' => $odp->nama,
                    'lokasi' => $odp->lokasi,
                    'pop' => $odp->pop?->nama,
                    'jumlah_port' => (int) $totalPorts,
                    'port_terpakai' => (int) $usedPorts,
                    'port_tersisa' => (int) $remaining,
                    'fill_percentage' => $fillRatio,
                    'marker_color' => $markerColor,
                ],
            ];
        });

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }
}