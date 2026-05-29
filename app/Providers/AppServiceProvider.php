<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;

use App\Models\HotspotUser;
use App\Models\PppoeUser;
use App\Models\Mikrotik;

use App\Observers\HotspotUserObserver;
use App\Observers\PPPoEUserObserver;

use App\Services\RadiusSyncService;
use App\Services\RadiusSessionService;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RadiusSyncService::class, fn () => new RadiusSyncService());
        $this->app->alias(RadiusSyncService::class, 'RadiusSync');

        $this->app->singleton(RadiusSessionService::class, fn () => new RadiusSessionService());
        $this->app->alias(RadiusSessionService::class, 'RadiusSession');
    }

    public function boot(): void
    {
        HotspotUser::observe(HotspotUserObserver::class);
        PppoeUser::observe(PPPoEUserObserver::class);
        // NasObserver::observe(Nas::class); // disabled - Nas model not yet created

        if ($this->app->environment('local', 'development')) {
            $this->enableObserverLogging();
        }
    }

    private function enableObserverLogging(): void
    {
        HotspotUser::creating(fn ($user) => Log::debug('OBSERVER: HotspotUser creating', [
            'username' => $user->username,
            'status' => $user->status,
            'profile_id' => $user->profile_id,
        ]));

        PppoeUser::creating(fn ($user) => Log::debug('OBSERVER: PppoeUser creating', [
            'username' => $user->username,
            'status' => $user->status,
        ]));

        Mikrotik::creating(fn ($m) => Log::debug('OBSERVER: Mikrotik creating', [
            'nama' => $m->nama,
            'ip_address' => $m->ip_address,
        ]));
    }
}
