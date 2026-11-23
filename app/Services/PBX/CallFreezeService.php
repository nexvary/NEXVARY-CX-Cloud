<?php

namespace App\Services\PBX;

use App\Models\CallFreeze;
use App\Models\Trunk;
use Illuminate\Support\Facades\Cache;

/**
 * Checks and manages the Emergency Communications Freeze state.
 *
 * Freeze state is kept in a fast Redis cache (key = call_freeze_active).
 * On cache miss the DB is consulted and the cache refreshed.
 *
 * Enforcement at the NEXVARY API layer covers:
 *   - Calls initiated via the web UI
 *   - Calls initiated via the Android/mobile API
 *   - Calls placed via the ESL originate path through Laravel
 *
 * Direct SIP clients that bypass NEXVARY and register to FreeSWITCH
 * directly are covered separately via a FreeSWITCH dialplan condition
 * written by activate()/deactivate() — see writeFsDialplanCondition().
 */
class CallFreezeService
{
    private const CACHE_TTL = 60;   // seconds
    private const CACHE_KEY_PREFIX = 'call_freeze_active:';

    // ── Query ─────────────────────────────────────────────────────────────────

    public function isTenantFrozen(string $tenantId): bool
    {
        return Cache::remember(
            static::CACHE_KEY_PREFIX . 'tenant:' . $tenantId,
            static::CACHE_TTL,
            fn () => CallFreeze::active()
                ->where('scope', 'tenant')
                ->where('tenant_id', $tenantId)
                ->exists()
        );
    }

    public function isGatewayFrozen(string $trunkId): bool
    {
        return Cache::remember(
            static::CACHE_KEY_PREFIX . 'gateway:' . $trunkId,
            static::CACHE_TTL,
            fn () => CallFreeze::active()
                ->where('scope', 'gateway')
                ->where('trunk_id', $trunkId)
                ->exists()
        );
    }

    /**
     * Returns true if an outbound call from $tenantId via $trunkId is frozen.
     * Tenant-level freeze takes precedence over gateway-level.
     */
    public function isCallBlocked(string $tenantId, string $trunkId): bool
    {
        return $this->isTenantFrozen($tenantId) || $this->isGatewayFrozen($trunkId);
    }

    public function activeFreezes(?string $tenantId = null): \Illuminate\Database\Eloquent\Collection
    {
        return CallFreeze::active()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->with(['tenant', 'trunk', 'activatedByUser'])
            ->latest('activated_at')
            ->get();
    }

    // ── Activate ──────────────────────────────────────────────────────────────

    public function activateTenantFreeze(string $tenantId, string $activatedBy, string $reason): CallFreeze
    {
        $freeze = CallFreeze::create([
            'scope'        => 'tenant',
            'tenant_id'    => $tenantId,
            'trunk_id'     => null,
            'reason'       => $reason,
            'activated_by' => $activatedBy,
            'activated_at' => now(),
            'active'       => true,
        ]);

        $this->flushCache('tenant', $tenantId);
        $this->writeFsDialplanCondition($tenantId, null);

        return $freeze;
    }

    public function activateGatewayFreeze(string $trunkId, string $activatedBy, string $reason): CallFreeze
    {
        $trunk = Trunk::findOrFail($trunkId);

        $freeze = CallFreeze::create([
            'scope'        => 'gateway',
            'tenant_id'    => $trunk->tenant_id,
            'trunk_id'     => $trunkId,
            'reason'       => $reason,
            'activated_by' => $activatedBy,
            'activated_at' => now(),
            'active'       => true,
        ]);

        $this->flushCache('gateway', $trunkId);
        $this->writeFsDialplanCondition(null, $trunkId);

        return $freeze;
    }

    // ── Deactivate ────────────────────────────────────────────────────────────

    public function deactivate(string $freezeId, string $deactivatedBy): CallFreeze
    {
        $freeze = CallFreeze::active()->findOrFail($freezeId);

        $freeze->update([
            'active'          => false,
            'deactivated_by'  => $deactivatedBy,
            'deactivated_at'  => now(),
        ]);

        if ($freeze->scope === 'tenant') {
            $this->flushCache('tenant', $freeze->tenant_id);
            $this->removeFsDialplanCondition($freeze->tenant_id, null);
        } else {
            $this->flushCache('gateway', $freeze->trunk_id);
            $this->removeFsDialplanCondition(null, $freeze->trunk_id);
        }

        return $freeze->fresh();
    }

    // ── Cache ─────────────────────────────────────────────────────────────────

    private function flushCache(string $type, ?string $id): void
    {
        if ($id) {
            Cache::forget(static::CACHE_KEY_PREFIX . $type . ':' . $id);
        }
    }

    // ── FreeSWITCH dialplan condition (direct SIP clients) ────────────────────

    /**
     * Writes a FreeSWITCH dialplan XML fragment that rejects outbound calls
     * when a freeze is active, covering SIP clients that bypass the NEXVARY API.
     *
     * The file is placed in a NEXVARY-managed include directory. FreeSWITCH
     * reloadxml picks it up without restart.
     *
     * File format: /etc/freeswitch/dialplan/nexvary_freeze_{scope}_{id}.xml
     */
    private function writeFsDialplanCondition(?string $tenantId, ?string $trunkId): void
    {
        $confDir = config('nexvary.freeswitch.conf_dir', '/etc/freeswitch');
        $dir     = "{$confDir}/dialplan/nexvary_freezes";

        if (PHP_OS_FAMILY === 'Windows' || ! is_dir($confDir)) {
            return; // Skip on dev/Windows — log only
        }

        @mkdir($dir, 0755, true);

        if ($tenantId) {
            $file = "{$dir}/tenant_{$tenantId}.xml";
            $xml  = $this->buildTenantFreezeXml($tenantId);
        } else {
            $file = "{$dir}/gateway_{$trunkId}.xml";
            $xml  = $this->buildGatewayFreezeXml($trunkId);
        }

        file_put_contents($file, $xml);
        $this->reloadFsXml();
    }

    private function removeFsDialplanCondition(?string $tenantId, ?string $trunkId): void
    {
        $confDir = config('nexvary.freeswitch.conf_dir', '/etc/freeswitch');
        $dir     = "{$confDir}/dialplan/nexvary_freezes";

        if (PHP_OS_FAMILY === 'Windows' || ! is_dir($confDir)) {
            return;
        }

        $file = $tenantId
            ? "{$dir}/tenant_{$tenantId}.xml"
            : "{$dir}/gateway_{$trunkId}.xml";

        if (file_exists($file)) {
            unlink($file);
        }

        $this->reloadFsXml();
    }

    private function buildTenantFreezeXml(string $tenantId): string
    {
        return <<<XML
        <extension name="nexvary_freeze_tenant_{$tenantId}" continue="false">
          <condition field="\${nexvary_tenant_id}" expression="^{$tenantId}$">
            <action application="respond" data="503 Outbound calls temporarily suspended"/>
            <action application="hangup"/>
          </condition>
        </extension>
        XML;
    }

    private function buildGatewayFreezeXml(string $trunkId): string
    {
        return <<<XML
        <extension name="nexvary_freeze_gateway_{$trunkId}" continue="false">
          <condition field="\${nexvary_gateway_id}" expression="^{$trunkId}$">
            <action application="respond" data="503 Gateway temporarily suspended"/>
            <action application="hangup"/>
          </condition>
        </extension>
        XML;
    }

    private function reloadFsXml(): void
    {
        try {
            $esl = app(\App\Services\PBX\FreeSwitchService::class);
            $esl->sendApi('reloadxml');
        } catch (\Throwable) {
            // Non-fatal: the dialplan change will be picked up on the next reload
        }
    }
}
