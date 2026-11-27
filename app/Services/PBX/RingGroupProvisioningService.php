<?php

namespace App\Services\PBX;

use App\Models\RingGroup;
use App\Models\Tenant;

/**
 * Writes ring group dialplan XML to the tenant domain context.
 * FreeSWITCH ring groups are implemented as dialplan extensions that use
 * the bridge application with a comma/colon separated dial string.
 */
class RingGroupProvisioningService
{
    public function __construct(
        private FusionPbxConfigService $config,
        private FreeSwitchService $fs,
    ) {}

    public function provision(RingGroup $ringGroup): void
    {
        $tenant = Tenant::find($ringGroup->tenant_id);
        if (! $tenant) {
            return;
        }

        $domain   = $tenant->sipDomain();
        $filename = "nexvary-rg-{$ringGroup->id}.xml";
        $xml      = $this->renderXml($ringGroup, $domain);
        $this->config->writeDialplanFile($domain, $filename, $xml);

        $this->reloadDialplan();
    }

    public function deprovision(RingGroup $ringGroup): void
    {
        $tenant = Tenant::find($ringGroup->tenant_id);
        if (! $tenant) {
            return;
        }
        $domain   = $tenant->sipDomain();
        $filename = "nexvary-rg-{$ringGroup->id}.xml";
        $this->config->removeDialplanFile($domain, $filename);
        $this->reloadDialplan();
    }

    private function renderXml(RingGroup $ringGroup, string $domain): string
    {
        $ext     = htmlspecialchars($ringGroup->extension, ENT_XML1 | ENT_QUOTES);
        $safeId  = htmlspecialchars($ringGroup->id, ENT_XML1 | ENT_QUOTES);
        $dom     = htmlspecialchars($domain, ENT_XML1 | ENT_QUOTES);
        $timeout = $ringGroup->timeout ?? 30;

        $members  = $ringGroup->members ?? [];
        $strategy = $ringGroup->strategy ?? 'simultaneous';

        // Build dial string based on strategy
        $memberParts = array_map(
            fn($m) => "user/{$m}@{$dom}",
            is_array($members) ? $members : []
        );

        if (empty($memberParts)) {
            $dialString = "user/operator@{$dom}";
        } elseif ($strategy === 'simultaneous') {
            $dialString = implode(',', $memberParts);
        } else {
            // sequential/ordered — use colon separator
            $dialString = implode(':', $memberParts);
        }

        $safeDialString = htmlspecialchars($dialString, ENT_XML1 | ENT_QUOTES);
        $destination    = htmlspecialchars($ringGroup->destination ?? 'voicemail', ENT_XML1 | ENT_QUOTES);

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<!-- NEXVARY CX Cloud — Ring Group {$ext} @ {$dom} -->
<include>
  <extension name="nexvary-rg-{$safeId}">
    <condition field="destination_number" expression="^{$ext}$">
      <action application="set" data="ring_group_id={$safeId}"/>
      <action application="set" data="call_timeout={$timeout}"/>
      <action application="bridge" data="{$safeDialString}"/>
      <action application="transfer" data="{$destination} XML {$dom}"/>
    </condition>
  </extension>
</include>
XML;
    }

    private function reloadDialplan(): void
    {
        if ($this->fs->connect()) {
            $this->fs->reloadDialplan();
            $this->fs->disconnect();
        }
    }
}
