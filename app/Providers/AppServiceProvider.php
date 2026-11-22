<?php

namespace App\Providers;

use App\Services\Install\Installation;
use App\Services\PBX\FreeSwitchService;
use App\Services\PBX\FusionPbxConfigService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FreeSwitchService::class, fn() => FreeSwitchService::make());
        $this->app->bind(FusionPbxConfigService::class, fn() => FusionPbxConfigService::make());
    }

    public function boot(): void
    {
        Installation::load();
    }
}
