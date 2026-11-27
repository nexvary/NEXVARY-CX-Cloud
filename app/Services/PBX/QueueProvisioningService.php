<?php

namespace App\Services\PBX;

use App\Models\CallQueue;
use App\Models\CallAgent;
use App\Models\Tenant;
use Illuminate\Support\Facades\File;

/**
 * Writes mod_fifo queue configuration to FreeSWITCH.
 * FreeSWITCH loads FIFO queue config from autoload_configs/fifo.conf.d/
 */
class QueueProvisioningService
{
    public function __construct(
        private FusionPbxConfigService $config,
        private FreeSwitchService $fs,
    ) {}

    public function provision(CallQueue $queue): void
    {
        $filename = "nexvary-queue-{$queue->id}.xml";
        $xml      = $this->renderXml($queue);
        $this->config->writeFifoConfig($filename, $xml);

        // Write dialplan entry that routes calls into the FIFO queue
        $tenant = Tenant::find($queue->tenant_id);
        if ($tenant) {
            $domain     = $tenant->sipDomain();
            $dpFile     = "nexvary-queue-dp-{$queue->id}.xml";
            $dpXml      = $this->renderDialplanXml($queue, $domain);
            $this->config->writeDialplanFile($domain, $dpFile, $dpXml);
            $this->fs->reloadDialplan();
        }
    }

    public function deprovision(CallQueue $queue): void
    {
        $this->config->removeFifoConfig("nexvary-queue-{$queue->id}.xml");
        $tenant = Tenant::find($queue->tenant_id);
        if ($tenant) {
            $this->config->removeDialplanFile($tenant->sipDomain(), "nexvary-queue-dp-{$queue->id}.xml");
        }
        $this->fs->reloadDialplan();
    }

    private function renderXml(CallQueue $queue): string
    {
        $safeName = htmlspecialchars("nexvary-q-{$queue->id}", ENT_XML1 | ENT_QUOTES);
        $timeout  = $queue->timeout ?? 300;

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<!-- NEXVARY CX Cloud — FIFO Queue {$queue->name} -->
<include>
  <fifo name="{$safeName}" importance="0">
    <member timeout="{$timeout}" simo="1" lag="0"/>
  </fifo>
</include>
XML;
    }

    private function renderDialplanXml(CallQueue $queue, string $domain): string
    {
        $safeId     = htmlspecialchars($queue->id, ENT_XML1 | ENT_QUOTES);
        $safeDomain = htmlspecialchars($domain, ENT_XML1 | ENT_QUOTES);
        $timeout    = $queue->timeout ?? 300;
        $moh        = htmlspecialchars($queue->moh_sound ?? 'local_stream://default', ENT_XML1 | ENT_QUOTES);

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<!-- NEXVARY CX Cloud — Queue dialplan entry for {$queue->name} -->
<include>
  <extension name="nexvary-q-{$safeId}">
    <condition field="\${domain_name}" expression="^{$safeDomain}$">
      <condition field="caller_id_number" expression="(.*)">
        <action application="set" data="fifo_music={$moh}"/>
        <action application="fifo" data="nexvary-q-{$safeId}@{$safeDomain} in {$moh} {$moh}"/>
      </condition>
    </condition>
  </extension>
</include>
XML;
    }


}
