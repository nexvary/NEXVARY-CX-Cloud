<?php

namespace App\Services\PBX;

use App\Models\BusinessHours;
use App\Models\DayNightMode;
use App\Models\Tenant;
use Illuminate\Support\Collection;

class BusinessHoursService
{
    public function __construct(private FusionPbxConfigService $config) {}

    public function list(string $tenantId): Collection
    {
        return BusinessHours::where('tenant_id', $tenantId)->orderBy('name')->get();
    }

    public function create(array $data): BusinessHours
    {
        $bh = BusinessHours::create($data);
        $this->writeDialplan($bh);
        return $bh;
    }

    public function update(BusinessHours $bh, array $data): BusinessHours
    {
        $bh->update($data);
        $this->writeDialplan($bh);
        return $bh;
    }

    public function delete(BusinessHours $bh): void
    {
        $this->removeDialplan($bh);
        $bh->delete();
    }

    public function listDayNight(string $tenantId): Collection
    {
        return DayNightMode::where('tenant_id', $tenantId)->orderBy('name')->get();
    }

    public function createDayNight(array $data): DayNightMode
    {
        return DayNightMode::create($data);
    }

    public function updateDayNight(DayNightMode $dnm, array $data): DayNightMode
    {
        $dnm->update($data);
        return $dnm;
    }

    public function toggleMode(DayNightMode $dnm): DayNightMode
    {
        $dnm->update(['mode' => $dnm->mode === 'day' ? 'night' : 'day']);
        return $dnm;
    }

    public function deleteDayNight(DayNightMode $dnm): void
    {
        $dnm->delete();
    }

    private function writeDialplan(BusinessHours $bh): void
    {
        if (! $bh->enabled) {
            $this->removeDialplan($bh);
            return;
        }

        $tenant = Tenant::find($bh->tenant_id);
        if (! $tenant) {
            return;
        }

        $domain   = $tenant->sipDomain();
        $filename = "nexvary-bh-{$bh->id}.xml";
        $xml      = $this->renderXml($bh, $domain);
        $this->config->writeDialplanFile($domain, $filename, $xml);
    }

    private function removeDialplan(BusinessHours $bh): void
    {
        $tenant = Tenant::find($bh->tenant_id);
        if (! $tenant) {
            return;
        }
        $domain   = $tenant->sipDomain();
        $filename = "nexvary-bh-{$bh->id}.xml";
        $this->config->removeDialplanFile($domain, $filename);
    }

    private function renderXml(BusinessHours $bh, string $domain): string
    {
        $safeId   = htmlspecialchars($bh->id, ENT_XML1 | ENT_QUOTES);
        $safeName = htmlspecialchars($bh->name, ENT_XML1 | ENT_QUOTES);
        $tz       = htmlspecialchars($bh->timezone, ENT_XML1 | ENT_QUOTES);

        $schedule = $bh->schedule ?? [];
        $dayMap   = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
        $wday     = [];
        foreach ($schedule as $slot) {
            $day = $slot['day'] ?? null;
            if ($day && isset($slot['open_time'], $slot['close_time'])) {
                $wday[] = "            <condition wday=\"{$day}\" time-of-day=\"{$slot['open_time']}-{$slot['close_time']}\"/>";
            }
        }
        $conditions = implode("\n", $wday);

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<include>
  <extension name="nexvary-bh-{$safeId}" continue="true">
    <condition field="\${domain_name}" expression="^{$domain}$">
{$conditions}
      <action application="set" data="business_hours_active=true"/>
      <anti-action application="set" data="business_hours_active=false"/>
    </condition>
  </extension>
</include>
XML;
    }
}
