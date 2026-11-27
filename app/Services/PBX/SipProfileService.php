<?php

namespace App\Services\PBX;

use App\Models\SipProfile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Manages FreeSWITCH sofia SIP profile XML and lifecycle.
 * Delegates XML rendering to FusionPbxConfigService.
 * Profiles written to: {conf_dir}/sip_profiles/nexvary-{type}-{id}.xml
 */
class SipProfileService
{
    public function __construct(
        private readonly string $confDir,
        private readonly FreeSwitchService $fs,
        private readonly FusionPbxConfigService $config,
    ) {}

    public static function make(): static
    {
        return new static(
            confDir: config("nexvary.freeswitch.conf_dir", "/etc/freeswitch"),
            fs: FreeSwitchService::make(),
            config: FusionPbxConfigService::make(),
        );
    }

    public function activate(SipProfile $profile): array
    {
        $this->write($profile);
        if (!$this->fs->connect()) {
            $this->deleteFile($profile);
            $profile->update(["enabled" => false]);
            return ["status" => "esl_unavailable", "activated" => false];
        }
        $name   = $this->profileName($profile);
        $result = $this->fs->api("sofia profile $name rescan");
        $this->fs->disconnect();
        if ($result === false) {
            $this->deleteFile($profile);
            $profile->update(["enabled" => false]);
            return ["status" => "reload_failed", "activated" => false];
        }
        $profile->update(["enabled" => true]);
        return ["status" => "activated", "activated" => true, "profile" => $name];
    }

    public function deactivate(SipProfile $profile): array
    {
        $name = $this->profileName($profile);
        if ($this->fs->connect()) {
            $this->fs->api("sofia profile $name stop");
            $this->fs->disconnect();
        }
        $this->deleteFile($profile);
        $profile->update(["enabled" => false]);
        return ["status" => "deactivated", "profile" => $name];
    }

    public function getStatus(SipProfile $profile): array
    {
        if (!$this->fs->connect()) {
            return ["running" => false, "status" => "esl_unavailable"];
        }
        $name = $this->profileName($profile);
        $raw  = $this->fs->api("sofia status profile $name");
        $this->fs->disconnect();
        if (!$raw || str_contains($raw, "Invalid")) {
            return ["running" => false, "status" => "not_loaded"];
        }
        $data = ["running" => true, "status" => "running"];
        if (preg_match("/Registrations\s+(\d+)/i", $raw, $m)) {
            $data["registrations"] = (int) $m[1];
        }
        if (preg_match("/Calls-In\s+(\d+)/i", $raw, $m)) {
            $data["calls_in"] = (int) $m[1];
        }
        return $data;
    }

    public function write(SipProfile $profile): void
    {
        $this->config->writeSipProfileConfig($profile);
    }

    public function deleteFile(SipProfile $profile): void
    {
        $this->config->deleteSipProfileConfig($profile);
    }

    private function profileName(SipProfile $profile): string
    {
        return "nexvary-" . $profile->type . "-" . $profile->id;
    }
}
