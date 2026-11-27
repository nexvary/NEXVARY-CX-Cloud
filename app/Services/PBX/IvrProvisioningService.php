<?php

namespace App\Services\PBX;

use App\Models\IvrMenu;
use App\Models\IvrEntry;
use App\Models\Tenant;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Writes IVR menu configuration to FreeSWITCH and triggers a configuration
 * reload via ESL. FreeSWITCH loads IVR menus from autoload_configs/ivr.conf.d/
 */
class IvrProvisioningService
{
    public function __construct(
        private FusionPbxConfigService $config,
        private FreeSwitchService $fs,
    ) {}

    public function provision(IvrMenu $menu): void
    {
        $entries = $menu->entries()
            ->orderBy('digit')
            ->get()
            ->map(fn($e) => [
                'digit'  => $e->digit,
                'action' => $e->action,
                'param'  => $e->param ?? '',
            ])
            ->toArray();

        $this->config->writeIvrMenu([
            'id'           => $menu->id,
            'name'         => $menu->name,
            'greeting_file'=> $menu->greeting_file,
            'timeout'      => ($menu->timeout ?? 10) * 1000,
            'max_retries'  => $menu->max_failures ?? 3,
            'entries'      => $entries,
        ]);

        $this->reloadIvr();
    }

    public function deprovision(IvrMenu $menu): void
    {
        $this->config->removeIvrMenu($menu->id);
        $this->reloadIvr();
    }

    private function reloadIvr(): void
    {
        if ($this->fs->connect()) {
            $this->fs->api('reloadxml');
            $this->fs->disconnect();
        }
    }
}
