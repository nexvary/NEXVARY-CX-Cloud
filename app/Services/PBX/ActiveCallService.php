<?php

namespace App\Services\PBX;

use App\Models\Extension;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;

/**
 * Active-call control via FreeSWITCH ESL.
 * All operations are tenant-scoped: tenant_id is verified against the channel
 * domain variable before accepting the UUID.
 */
class ActiveCallService
{
    public function __construct(private FreeSwitchService $fs) {}

    // ── Active calls ──────────────────────────────────────────────────────────

    /**
     * List active calls belonging to a tenant (filtered by domain).
     */
    public function getCallsForTenant(string $tenantId): array
    {
        $tenant = Tenant::find($tenantId);
        if (! $tenant) {
            return [];
        }
        $domain = $tenant->sipDomain();

        if (! $this->fs->connect()) {
            return [];
        }

        try {
            $calls = $this->fs->getActiveCalls();
        } finally {
            $this->fs->disconnect();
        }

        return array_filter($calls, fn($c) => ($c['domain'] ?? '') === $domain);
    }

    // ── Hold / Resume ─────────────────────────────────────────────────────────

    public function hold(string $uuid, string $tenantId, ?string $mohClass = null): bool
    {
        if (! $this->verifyTenantOwnership($uuid, $tenantId)) {
            return false;
        }

        if (! $this->fs->connect()) {
            return false;
        }

        try {
            if ($mohClass) {
                $this->fs->setMoh($uuid, $mohClass);
            }
            return $this->fs->holdCall($uuid);
        } finally {
            $this->fs->disconnect();
        }
    }

    public function resume(string $uuid, string $tenantId): bool
    {
        if (! $this->verifyTenantOwnership($uuid, $tenantId)) {
            return false;
        }

        if (! $this->fs->connect()) {
            return false;
        }

        try {
            return $this->fs->resumeCall($uuid);
        } finally {
            $this->fs->disconnect();
        }
    }

    // ── Transfer ──────────────────────────────────────────────────────────────

    /**
     * Blind transfer: redirect the call to a new destination without consultation.
     */
    public function blindTransfer(string $uuid, string $destination, string $tenantId): bool
    {
        if (! $this->verifyTenantOwnership($uuid, $tenantId)) {
            return false;
        }

        $tenant = Tenant::find($tenantId);
        if (! $tenant) {
            return false;
        }

        if (! $this->fs->connect()) {
            return false;
        }

        try {
            return $this->fs->transferCall($uuid, $destination, $tenant->sipDomain());
        } finally {
            $this->fs->disconnect();
        }
    }

    /**
     * Attended (consultative) transfer: bridge original call with the consult leg.
     */
    public function attendedTransfer(string $origUuid, string $consultUuid, string $tenantId): bool
    {
        if (! $this->verifyTenantOwnership($origUuid, $tenantId)) {
            return false;
        }
        if (! $this->verifyTenantOwnership($consultUuid, $tenantId)) {
            return false;
        }

        if (! $this->fs->connect()) {
            return false;
        }

        try {
            return $this->fs->attendedTransfer($origUuid, $consultUuid);
        } finally {
            $this->fs->disconnect();
        }
    }

    // ── Parking ───────────────────────────────────────────────────────────────

    /**
     * Park a call in the valet lot.
     * Returns the slot number ('') on failure.
     */
    public function park(string $uuid, string $tenantId): string
    {
        if (! $this->verifyTenantOwnership($uuid, $tenantId)) {
            return '';
        }

        if (! $this->fs->connect()) {
            return '';
        }

        try {
            return $this->fs->parkCall($uuid);
        } finally {
            $this->fs->disconnect();
        }
    }

    /**
     * Retrieve a parked call by bridge with the retriever's UUID.
     */
    public function pickup(string $retrieveUuid, string $slot, string $tenantId): bool
    {
        if (! $this->verifyTenantOwnership($retrieveUuid, $tenantId)) {
            return false;
        }

        if (! $this->fs->connect()) {
            return false;
        }

        try {
            return $this->fs->pickupParked($retrieveUuid, $slot);
        } finally {
            $this->fs->disconnect();
        }
    }

    /**
     * List parked calls for a tenant.
     */
    public function listParked(string $tenantId): array
    {
        $tenant = Tenant::find($tenantId);
        if (! $tenant) {
            return [];
        }

        if (! $this->fs->connect()) {
            return [];
        }

        try {
            return $this->fs->listParkedCalls();
        } finally {
            $this->fs->disconnect();
        }
    }

    // ── MoH ──────────────────────────────────────────────────────────────────

    public function applyMoh(string $uuid, string $mohClass, string $tenantId): bool
    {
        if (! $this->verifyTenantOwnership($uuid, $tenantId)) {
            return false;
        }

        if (! $this->fs->connect()) {
            return false;
        }

        try {
            return $this->fs->setMoh($uuid, $mohClass);
        } finally {
            $this->fs->disconnect();
        }
    }

    // ── Tenant verification ────────────────────────────────────────────────────

    /**
     * Verify that a call UUID belongs to the given tenant's domain.
     * This prevents one tenant from controlling another tenant's calls.
     */
    private function verifyTenantOwnership(string $uuid, string $tenantId): bool
    {
        $tenant = Tenant::find($tenantId);
        if (! $tenant) {
            Log::warning('ActiveCallService: tenant not found', ['tenant_id' => $tenantId]);
            return false;
        }

        $domain = $tenant->sipDomain();

        if (! $this->fs->connect()) {
            return false;
        }

        try {
            $result = $this->fs->api("uuid_getvar {$uuid} domain_name");
            $callDomain = trim((string) $result);
        } finally {
            $this->fs->disconnect();
        }

        if ($callDomain !== $domain) {
            Log::warning('ActiveCallService: cross-tenant call access denied', [
                'uuid'          => $uuid,
                'tenant_domain' => $domain,
                'call_domain'   => $callDomain,
            ]);
            return false;
        }

        return true;
    }
}
