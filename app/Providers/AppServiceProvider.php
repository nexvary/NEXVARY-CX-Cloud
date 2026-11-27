<?php

namespace App\Providers;

use App\Services\Install\Installation;
use App\Services\PBX\FreeSwitchService;
use App\Services\PBX\FusionPbxConfigService;
use App\Services\PBX\SipProfileService;
use App\Services\PBX\NumberTranslationService;
use App\Services\PBX\DeviceProvisionService;
use App\Services\PBX\DialplanRuleService;
use App\Services\PBX\ConfigValidationService;
use App\Http\Controllers\Provision\ProvisionController;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FreeSwitchService::class, fn() => FreeSwitchService::make());
        $this->app->bind(FusionPbxConfigService::class, fn() => FusionPbxConfigService::make());
        $this->app->bind(SipProfileService::class, fn() => SipProfileService::make());
        $this->app->bind(NumberTranslationService::class, fn() => NumberTranslationService::make());
        $this->app->bind(DeviceProvisionService::class, fn() => new DeviceProvisionService());
        $this->app->bind(DialplanRuleService::class, fn() => DialplanRuleService::make());
        $this->app->bind(ProvisionController::class, fn() => new ProvisionController(app(DeviceProvisionService::class)));
    }

    public function boot(): void
    {
        Installation::load();
    }
}
