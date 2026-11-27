<?php

namespace App\Services\PBX;

use App\Models\CallBlacklist;
use App\Models\Tenant;
use Illuminate\Support\Collection;

class BlacklistService
{
    public function __construct(private FusionPbxConfigService $config) {}

    public function list(string $tenantId): Collection
    {
        return CallBlacklist::where('tenant_id', $tenantId)
            ->orderBy('pattern')
            ->get();
    }

    public function create(array $data): CallBlacklist
    {
        $entry = CallBlacklist::create($data);
        $this->writeDialplan($entry);
        return $entry;
    }

    public function update(CallBlacklist $entry, array $data): CallBlacklist
    {
        $entry->update($data);
        $this->writeDialplan($entry);
        return $entry;
    }

    public function delete(CallBlacklist $entry): void
    {
        $this->removeDialplan($entry);
        $entry->delete();
    }

    private function writeDialplan(CallBlacklist $entry): void
    {
        if (! $entry->enabled) {
            $this->removeDialplan($entry);
            return;
        }

        $tenant = Tenant::find($entry->tenant_id);
        if (! $tenant) {
            return;
        }

        $domain   = $tenant->sipDomain();
        $filename = "nexvary-blacklist-{$entry->id}.xml";
        $xml      = $this->renderXml($entry, $domain);
        $this->config->writeDialplanFile($domain, $filename, $xml);
    }

    private function removeDialplan(CallBlacklist $entry): void
    {
        $tenant = Tenant::find($entry->tenant_id);
        if (! $tenant) {
            return;
        }
        $domain   = $tenant->sipDomain();
        $filename = "nexvary-blacklist-{$entry->id}.xml";
        $this->config->removeDialplanFile($domain, $filename);
    }

    private function renderXml(CallBlacklist $entry, string $domain): string
    {
        $condition = match ($entry->pattern_type) {
            'prefix' => "^{$entry->pattern}",
            'regex'  => $entry->pattern,
            default  => "^{$entry->pattern}$",
        };

        $field = match ($entry->direction) {
            'outbound' => 'destination_number',
            default    => 'caller_id_number',
        };

        $safeCondition = htmlspecialchars($condition, ENT_XML1 | ENT_QUOTES);
        $safeId        = htmlspecialchars($entry->id, ENT_XML1 | ENT_QUOTES);

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<include>
  <extension name="nexvary-blacklist-{$safeId}" continue="false">
    <condition field="{$field}" expression="{$safeCondition}">
      <action application="answer"/>
      <action application="sleep" data="1000"/>
      <action application="hangup" data="CALL_REJECTED"/>
    </condition>
  </extension>
</include>
XML;
    }
}
