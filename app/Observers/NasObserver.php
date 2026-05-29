<?php

namespace App\Observers;

use App\Models\Mikrotik;
use App\Models\RadNas;
use App\Services\RadiusSyncService;
use Illuminate\Support\Facades\Log;

class NasObserver
{
    private RadiusSyncService $radiusSync;

    public function __construct(RadiusSyncService $radiusSync)
    {
        $this->radiusSync = $radiusSync;
    }

    public function created(Mikrotik $mikrotik): void
    {
        if (empty($mikrotik->ip_address)) {
            return;
        }

        try {
            RadNas::syncFromMikrotik($mikrotik);
        } catch (\Exception $e) {
            Log::error("RADIUS OBSERVER: Failed to sync new Mikrotik {$mikrotik->nama}: {$e->getMessage()}");
        }
    }

    public function updated(Mikrotik $mikrotik): void
    {
        $changedFields = $mikrotik->getChanges();
        $syncFields = ['ip_address', 'nama', 'script', 'status'];

        $needsSync = false;
        foreach ($syncFields as $field) {
            if (isset($changedFields[$field])) {
                $needsSync = true;
                break;
            }
        }

        if (!$needsSync) {
            return;
        }

        try {
            if (isset($changedFields['ip_address']) && $changedFields['ip_address'] !== $mikrotik->ip_address) {
                RadNas::where('nasname', $changedFields['ip_address'])->delete();
            }

            RadNas::syncFromMikrotik($mikrotik);
        } catch (\Exception $e) {
            Log::error("RADIUS OBSERVER: Failed to update Mikrotik {$mikrotik->nama}: {$e->getMessage()}");
        }
    }

    public function deleted(Mikrotik $mikrotik): void
    {
        try {
            RadNas::where('nasname', $mikrotik->ip_address)->delete();
        } catch (\Exception $e) {
            Log::error("RADIUS OBSERVER: Failed to delete Mikrotik {$mikrotik->nama} from radnas: {$e->getMessage()}");
        }
    }

    public function restored(Mikrotik $mikrotik): void
    {
        if (!empty($mikrotik->ip_address)) {
            try {
                RadNas::syncFromMikrotik($mikrotik);
            } catch (\Exception $e) {
                Log::error("RADIUS OBSERVER: Failed to sync restored Mikrotik {$mikrotik->nama}: {$e->getMessage()}");
            }
        }
    }
}
