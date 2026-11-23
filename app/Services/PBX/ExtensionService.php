<?php

namespace App\Services\PBX;

use App\Models\Extension;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExtensionService
{
    public function __construct(
        private readonly FusionPbxConfigService $config,
        private readonly FreeSwitchService $fs,
    ) {}

    public function list(array $filters = []): Collection
    {
        return Extension::query()
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('number', 'like', "%{$search}%")
                      ->orWhere('description', 'ilike', "%{$search}%");
                });
            })
            ->when($filters['enabled'] ?? null, fn($q, $v) => $q->where('enabled', $v === 'true'))
            ->orderBy('number')
            ->get();
    }

    public function create(array $data): Extension
    {
        return DB::transaction(function () use ($data) {
            $sipPassword = $data['sip_password'] ?? Str::random(24);

            $extension = Extension::create([
                'number'          => $data['number'],
                'description'     => $data['description'] ?? '',
                'sip_password'    => $this->hashPassword($sipPassword),
                'voicemail_pin'   => $data['voicemail_pin'] ?? random_int(1000, 9999),
                'call_timeout'    => $data['call_timeout'] ?? 30,
                'forward_enabled' => false,
                'dnd_enabled'     => false,
                'enabled'         => true,
                'user_id'         => $data['user_id'] ?? null,
            ]);

            $this->config->writeExtensionConfig($extension, $sipPassword);
            $this->fs->reloadDialplan();

            return $extension->fresh();
        });
    }

    public function update(Extension $extension, array $data): Extension
    {
        return DB::transaction(function () use ($extension, $data) {
            $sipPassword = null;
            if (!empty($data['sip_password'])) {
                $sipPassword = $data['sip_password'];
                $data['sip_password'] = $this->hashPassword($sipPassword);
            } else {
                unset($data['sip_password']);
            }

            $extension->update($data);

            if ($sipPassword !== null) {
                $this->config->writeExtensionConfig($extension, $sipPassword);
                $this->fs->reloadDialplan();
            }

            return $extension->fresh();
        });
    }

    public function delete(Extension $extension): void
    {
        DB::transaction(function () use ($extension) {
            $this->config->deleteExtensionConfig($extension);
            $extension->delete();
            $this->fs->reloadDialplan();
        });
    }

    public function setDnd(Extension $extension, bool $enabled): void
    {
        $extension->update(['dnd_enabled' => $enabled]);
        $this->config->updateExtensionVariable($extension, 'do_not_disturb', $enabled ? 'true' : 'false');
    }

    public function setForward(Extension $extension, ?string $destination): void
    {
        $extension->update([
            'forward_enabled'     => $destination !== null,
            'forward_destination' => $destination,
        ]);
        $this->config->updateExtensionVariable(
            $extension,
            'call_forward_all',
            $destination ?? ''
        );
        $this->config->updateExtensionVariable(
            $extension,
            'call_forward_all_enabled',
            $destination !== null ? 'true' : 'false'
        );
    }

    /**
     * Removes the FreeSWITCH directory config for a disabled extension.
     * Without a directory entry, FreeSWITCH will return 403 to SIP REGISTER.
     */
    public function disableInFreeSWITCH(Extension $extension): void
    {
        $this->config->deleteExtensionConfig($extension);
        $this->fs->reloadDialplan();
    }

    public function getRegistrationStatus(Extension $extension): array
    {
        if (!$this->fs->connect()) {
            return ['registered' => false, 'status' => 'pbx_unavailable'];
        }

        $registrations = $this->fs->getRegistrations();
        $this->fs->disconnect();

        foreach ($registrations as $reg) {
            if (($reg['user'] ?? '') === $extension->number) {
                return [
                    'registered' => true,
                    'ip'         => $reg['network_ip'] ?? null,
                    'agent'      => $reg['user_agent'] ?? null,
                    'expires'    => $reg['expires'] ?? null,
                ];
            }
        }

        return ['registered' => false, 'status' => 'not_registered'];
    }

    private function hashPassword(string $password): string
    {
        return hash('sha256', $password);
    }
}
