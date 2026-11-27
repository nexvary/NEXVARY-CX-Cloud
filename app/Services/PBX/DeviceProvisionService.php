<?php
namespace App\Services\PBX;

use App\Models\DeviceProvision;
use App\Models\Extension;

/**
 * Generates phone-vendor configuration for auto-provisioning.
 * URL: GET /provision/{raw_token}  (rate-limited, no session auth)
 * Never embeds the SIP password in URLs or logs.
 */
class DeviceProvisionService
{
    public function render(DeviceProvision $provision): string
    {
        $ext = $provision->extension;
        if (!$ext) {
            return $this->renderEmpty();
        }
        $sipHost = config('nexvary.freeswitch.sip_host', '127.0.0.1');
        $sipPort = config('nexvary.freeswitch.sip_port', 5060);
        return match ($provision->device_type) {
            'yealink'     => $this->renderYealink($ext, $sipHost, $sipPort, $provision->settings ?? []),
            'polycom'     => $this->renderPolycom($ext, $sipHost, $sipPort, $provision->settings ?? []),
            'grandstream' => $this->renderGrandstream($ext, $sipHost, $sipPort, $provision->settings ?? []),
            'cisco'       => $this->renderCisco($ext, $sipHost, $sipPort, $provision->settings ?? []),
            'snom'        => $this->renderSnom($ext, $sipHost, $sipPort, $provision->settings ?? []),
            default       => $this->renderGeneric($ext, $sipHost, $sipPort, $provision->settings ?? []),
        };
    }

    public function contentType(string $deviceType): string { return 'text/xml'; }

    public function filename(DeviceProvision $provision): string
    {
        $mac = str_replace(':', '', $provision->mac_address ?? $provision->id);
        return match ($provision->device_type) {
            'yealink'     => "{$mac}.cfg",
            'grandstream' => "cfg{$mac}.xml",
            'polycom'     => "{$mac}-phone.cfg",
            default       => "provision-{$provision->id}.xml",
        };
    }

    private function x(string $v): string { return htmlspecialchars($v, ENT_XML1); }

    private function renderYealink(Extension $ext, string $host, int $port, array $s): string
    {
        $n = $this->x($ext->number);
        $d = $this->x($ext->display_name ?? $ext->number);
        $h = $this->x($host);
        $c = $this->x($s['codec_list'] ?? 'PCMU/8000/1,PCMA/8000/1,G722/16000/1');
        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
"
            . "<!-- NEXVARY CX Cloud - Yealink Provision | Ext: {$n} -->
"
            . "<yealinkIPPhoneConfig>
"
            . "  <account><id>1</id><enable>1</enable><label>{$d}</label>
"
            . "    <display_name>{$d}</display_name><auth_name>{$n}</auth_name>
"
            . "    <user_name>{$n}</user_name>
"
            . "    <sip_server_host>{$h}</sip_server_host>
"
            . "    <sip_server_port>{$port}</sip_server_port></account>
"
            . "  <codec><account_id>1</account_id><codecs>{$c}</codecs></codec>
"
            . "</yealinkIPPhoneConfig>
";
    }

    private function renderPolycom(Extension $ext, string $host, int $port, array $s): string
    {
        $n = $this->x($ext->number);
        $d = $this->x($ext->display_name ?? $ext->number);
        $h = $this->x($host);
        return "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>
"
            . "<!-- NEXVARY CX Cloud - Polycom Provision | Ext: {$n} -->
"
            . "<phone1>
"
            . "  <reg reg.1.address=\"{$n}\" reg.1.auth.userId=\"{$n}\"
"
            . "       reg.1.label=\"{$d}\" reg.1.displayName=\"{$d}\"
"
            . "       reg.1.server.1.address=\"{$h}\" reg.1.server.1.port=\"{$port}\"
"
            . "       reg.1.server.1.transport=\"UDPonly\" reg.1.enable=\"1\"/>
"
            . "</phone1>
";
    }

    private function renderGrandstream(Extension $ext, string $host, int $port, array $s): string
    {
        $n = $this->x($ext->number);
        $d = $this->x($ext->display_name ?? $ext->number);
        $h = $this->x($host);
        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
"
            . "<!-- NEXVARY CX Cloud - Grandstream Provision | Ext: {$n} -->
"
            . "<gs_provision version=\"1\">
"
            . "  <config><item_id>P270</item_id><item_value>{$h}</item_value></config>
"
            . "  <config><item_id>P2378</item_id><item_value>{$port}</item_value></config>
"
            . "  <config><item_id>P302</item_id><item_value>{$n}</item_value></config>
"
            . "  <config><item_id>P303</item_id><item_value>{$n}</item_value></config>
"
            . "  <config><item_id>P3</item_id><item_value>{$d}</item_value></config>
"
            . "</gs_provision>
";
    }

    private function renderCisco(Extension $ext, string $host, int $port, array $s): string
    {
        $n = $this->x($ext->number);
        $d = $this->x($ext->display_name ?? $ext->number);
        $h = $this->x($host);
        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
"
            . "<!-- NEXVARY CX Cloud - Cisco SPA Provision | Ext: {$n} -->
"
            . "<flat-profile>
"
            . "  <Proxy_1_>{$h}</Proxy_1_><SIP_Port_1_>{$port}</SIP_Port_1_>
"
            . "  <Display_Name_1_>{$d}</Display_Name_1_>
"
            . "  <User_ID_1_>{$n}</User_ID_1_><Auth_ID_1_>{$n}</Auth_ID_1_>
"
            . "  <Line_Enable_1_>yes</Line_Enable_1_>
"
            . "</flat-profile>
";
    }

    private function renderSnom(Extension $ext, string $host, int $port, array $s): string
    {
        $n = $this->x($ext->number);
        $d = $this->x($ext->display_name ?? $ext->number);
        $h = $this->x($host);
        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
"
            . "<!-- NEXVARY CX Cloud - Snom Provision | Ext: {$n} -->
"
            . "<settings>
"
            . "  <SIP_ID>{$n}</SIP_ID><SIP_Display_Name>{$d}</SIP_Display_Name>
"
            . "  <SIP_Domain>{$h}</SIP_Domain><SIP_Domain_Port>{$port}</SIP_Domain_Port>
"
            . "  <SIP_Auth_ID>{$n}</SIP_Auth_ID>
"
            . "</settings>
";
    }

    private function renderGeneric(Extension $ext, string $host, int $port, array $s): string
    {
        $n = $this->x($ext->number);
        $d = $this->x($ext->display_name ?? $ext->number);
        $h = $this->x($host);
        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
"
            . "<!-- NEXVARY CX Cloud - Generic SIP Provision | Ext: {$n} -->
"
            . "<provision>
"
            . "  <sip><server>{$h}</server><port>{$port}</port>
"
            . "    <username>{$n}</username><auth_username>{$n}</auth_username>
"
            . "    <display_name>{$d}</display_name></sip>
"
            . "</provision>
";
    }

    private function renderEmpty(): string
    {
        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<!-- NEXVARY CX Cloud - Provision: no extension assigned -->
<provision/>
";
    }
}
