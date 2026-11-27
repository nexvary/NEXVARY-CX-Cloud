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
use App\Services\PBX\ActiveCallService;
use App\Services\PBX\BlacklistService;
use App\Services\PBX\BusinessHoursService;
use App\Services\PBX\MohService;
use App\Services\PBX\IvrProvisioningService;
use App\Services\PBX\RingGroupProvisioningService;
use App\Services\PBX\QueueProvisioningService;
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
        $this->app->bind(ActiveCallService::class, fn() => new ActiveCallService(app(FreeSwitchService::class)));
        $this->app->bind(BlacklistService::class, fn() => new BlacklistService(app(FusionPbxConfigService::class)));
        $this->app->bind(BusinessHoursService::class, fn() => new BusinessHoursService(app(FusionPbxConfigService::class)));
        $this->app->bind(MohService::class, fn() => new MohService());
        $this->app->bind(IvrProvisioningService::class, fn() => new IvrProvisioningService(app(FusionPbxConfigService::class), app(FreeSwitchService::class)));
        $this->app->bind(RingGroupProvisioningService::class, fn() => new RingGroupProvisioningService(app(FusionPbxConfigService::class), app(FreeSwitchService::class)));
        $this->app->bind(QueueProvisioningService::class, fn() => new QueueProvisioningService(app(FusionPbxConfigService::class), app(FreeSwitchService::class)));
        $this->app->bind(ProvisionController::class, fn() => new ProvisionController(app(DeviceProvisionService::class)));
    }

    public function boot(): void
    {
        Installation::load();
    }
}
