<?php

namespace App\Providers;

use App\Services\Install\Installation;
use App\Services\PBX\FreeSwitchService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FreeSwitchService::class, fn() => FreeSwitchService::make());
    }

    public function boot(): void
    {
        Installation::load();
    }
}
