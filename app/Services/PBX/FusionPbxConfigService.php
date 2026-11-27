<?php

namespace App\Services\PBX;

use App\Models\Extension;
use App\Models\InboundRoute;
use App\Models\OutboundRoute;
use App\Models\Trunk;
use App\Models\SipProfile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Writes FreeSWITCH configuration files in the FusionPBX directory layout.
 *
 * Key design (following the FusionPBX multi-tenancy model):
 *
 *   Extensions:  directory/{sip_domain}/{number}.xml
 *                user_context = {sip_domain}   (not "default")
 *
 *   Inbound:     dialplan/public/nexvary-inbound-{id}.xml
 *                transfer target:  {dest} XML {sip_domain}
 *
 *   Outbound:    dialplan/{sip_domain}/nexvary-outbound-{id}.xml
 *
 *   Gateways:    sip_profiles/external/nexvary-trunk-{id}.xml
 *
 * This isolates each tenant into its own FreeSWITCH context so two tenants
 * can share the same extension number without conflicts.
 *
 * FusionPBX reference implementation studied at commit referenced in
 * docs/OPEN_SOURCE_COMPONENTS.md. Configuration patterns adapted, not copied.
 */
class FusionPbxConfigService
{
    public function __construct(
        private readonly string $confDir,
    ) {}

    public static function make(): static
    {
        return new static(
            confDir: config('nexvary.freeswitch.conf_dir', '/etc/freeswitch'),
        );
    }

    // ── Extensions ────────────────────────────────────────────────────────────

    /**
     * Write the FreeSWITCH directory entry for an extension.
     *
     * @param string $domain     The tenant's SIP domain (e.g. company.nexvary.cloud)
     *                           Used as both the directory path and user_context value.
     * @param string $sipPassword  Plaintext SIP credential — stored in the FS config file only.
     */
    public function writeExtensionConfig(Extension $extension, string $sipPassword, string $domain = 'default'): void
    {
        $dir = "{$this->confDir}/directory/{$domain}";
        $this->ensureDir($dir);

        $xml  = $this->renderExtensionXml($extension, $sipPassword, $domain);
        $path = "{$dir}/{$extension->number}.xml";

        File::put($path, $xml);
        Log::info("FreeSWITCH extension config written: {$path}", ['domain' => $domain]);
    }

    public function deleteExtensionConfig(Extension $extension, string $domain = 'default'): void
    {
        $path = "{$this->confDir}/directory/{$domain}/{$extension->number}.xml";
        if (File::exists($path)) {
            File::delete($path);
            Log::info("FreeSWITCH extension config removed: {$path}");
        }

        // Also remove from legacy default path if it exists (migration safety)
        $legacy = "{$this->confDir}/directory/default/{$extension->number}.xml";
        if (File::exists($legacy)) {
            File::delete($legacy);
        }
    }

    public function updateExtensionVariable(Extension $extension, string $key, string $value, string $domain = 'default'): void
    {
        $path = "{$this->confDir}/directory/{$domain}/{$extension->number}.xml";
        if (!File::exists($path)) {
            return;
        }

        $xml = File::get($path);
        if (preg_match('/<variable name="' . preg_quote($key, '/') . '"/', $xml)) {
            $xml = preg_replace(
                '/<variable name="' . preg_quote($key, '/') . '" value="[^"]*"/',
                "<variable name=\"{$key}\" value=\"{$value}\"",
                $xml
            );
        } else {
            $xml = str_replace(
                '</variables>',
                "  <variable name=\"{$key}\" value=\"{$value}\"/>\n</variables>",
                $xml
            );
        }

        File::put($path, $xml);
    }

    // ── Domain dialplan context ───────────────────────────────────────────────

    /**
     * Write the per-tenant dialplan context directory.
     * FreeSWITCH loads dialplan/{domain}/*.xml as the domain's context.
     * This ensures the directory exists so outbound routes land in the right context.
     */
    public function ensureDomainDialplanDir(string $domain): void
    {
        $this->ensureDir("{$this->confDir}/dialplan/{$domain}");
    }

    // ── Trunks / Gateways ─────────────────────────────────────────────────────

    /**
     * @param string $password  Plaintext SIP credential — stored in the FS config file only,
     *                          never persisted to the database.
     */
    public function writeTrunkConfig(Trunk $trunk, string $password = ''): void
    {
        $dir = "{$this->confDir}/sip_profiles/external";
        $this->ensureDir($dir);

        $xml  = $this->renderGatewayXml($trunk, $password);
        $path = "{$dir}/nexvary-trunk-{$trunk->id}.xml";

        File::put($path, $xml);
        Log::info("FreeSWITCH trunk config written: {$path}");
    }

    public function deleteTrunkConfig(Trunk $trunk): void
    {
        $path = "{$this->confDir}/sip_profiles/external/nexvary-trunk-{$trunk->id}.xml";
        if (File::exists($path)) {
            File::delete($path);
        }
    }

    /** Alias used by TrunkController. */
    public function deleteTrunk(Trunk $trunk): void
    {
        $this->deleteTrunkConfig($trunk);
    }

    // ── Dialplan — Inbound Routes ─────────────────────────────────────────────

    /**
     * Write an inbound (public context) dialplan extension.
     *
     * @param array $route  Must contain: id, did, destination, domain
     *                      'domain' is the tenant SIP domain used as transfer context.
     */
    public function writeInboundRoute(array $route): void
    {
        $dir = "{$this->confDir}/dialplan/public";
        $this->ensureDir($dir);

        $xml  = $this->renderInboundRouteXml($route);
        $path = "{$dir}/nexvary-inbound-{$route['id']}.xml";
        File::put($path, $xml);
        Log::info("FreeSWITCH inbound route written: {$path}", ['domain' => $route['domain'] ?? 'default']);
    }

    public function deleteInboundRoute(array $route): void
    {
        $path = "{$this->confDir}/dialplan/public/nexvary-inbound-{$route['id']}.xml";
        if (File::exists($path)) {
            File::delete($path);
        }
    }

    // ── Dialplan — Outbound Routes ────────────────────────────────────────────

    /**
     * Write an outbound route into the tenant's domain dialplan context.
     *
     * @param array $route  Must contain: id, pattern, gateway, domain
     */
    public function writeOutboundRoute(array $route): void
    {
        $domain = $route['domain'] ?? 'default';
        $dir    = "{$this->confDir}/dialplan/{$domain}";
        $this->ensureDir($dir);

        $xml  = $this->renderOutboundRouteXml($route);
        $path = "{$dir}/nexvary-outbound-{$route['id']}.xml";
        File::put($path, $xml);
        Log::info("FreeSWITCH outbound route written: {$path}", ['domain' => $domain]);
    }

    public function deleteOutboundRoute(array $route): void
    {
        $domain = $route['domain'] ?? 'default';
        $path   = "{$this->confDir}/dialplan/{$domain}/nexvary-outbound-{$route['id']}.xml";
        if (File::exists($path)) {
            File::delete($path);
        }

        // Also check default path (migration safety)
        $legacy = "{$this->confDir}/dialplan/default/nexvary-outbound-{$route['id']}.xml";
        if (File::exists($legacy)) {
            File::delete($legacy);
        }
    }

    // ── CDR webhook ──────────────────────────────────────────────────────────

    /**
     * Write the xml_cdr.conf.xml that makes FreeSWITCH POST call records to Laravel.
     *
     * @param string $appUrl   The public base URL of the NEXVARY app (e.g. https://cx.example.com)
     * @param string $secret   The NEXVARY_CDR_SECRET value (never logged)
     */
    public function writeCdrConfig(string $appUrl, string $secret): void
    {
        $url     = rtrim($appUrl, '/') . '/webhooks/freeswitch/cdr';
        $encoded = base64_encode('nexvary:' . $secret);

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<!-- NEXVARY CX Cloud — CDR webhook | Generated: {$this->timestamp()} -->
<configuration name="xml_cdr.conf" description="XML CDR Config">
  <settings>
    <param name="url" value="{$url}"/>
    <param name="retries" value="3"/>
    <param name="delay" value="5"/>
    <param name="log-http-and-xml" value="false"/>
    <param name="auth-scheme" value="basic"/>
    <param name="credentials" value="{$encoded}"/>
    <param name="encode" value="true"/>
    <param name="log-errors" value="true"/>
    <param name="prefix-a-leg" value="true"/>
    <param name="log-b-leg" value="false"/>
  </settings>
</configuration>
XML;

        $path = "{$this->confDir}/autoload_configs/xml_cdr.conf.xml";
        File::put($path, $xml);
        Log::info("FreeSWITCH CDR config written: {$path}");
    }

    // ── IVR ───────────────────────────────────────────────────────────────────

    public function writeIvrMenu(array $menu): void
    {
        $dir  = "{$this->confDir}/autoload_configs";
        $xml  = $this->renderIvrMenuXml($menu);
        $path = "{$dir}/ivr.conf.d/nexvary-ivr-{$menu['id']}.xml";
        $this->ensureDir(dirname($path));
        File::put($path, $xml);
    }

    // ── Templates ─────────────────────────────────────────────────────────────

    private function renderExtensionXml(Extension $extension, string $password, string $domain): string
    {
        $number  = htmlspecialchars($extension->number, ENT_XML1);
        $dom     = htmlspecialchars($domain, ENT_XML1);
        $vmPin   = htmlspecialchars((string) $extension->voicemail_pin, ENT_XML1);
        $dnd     = $extension->dnd_enabled ? 'true' : 'false';
        $fwd     = htmlspecialchars($extension->forward_destination ?? '', ENT_XML1);
        $fwdEnabled = $extension->forward_enabled ? 'true' : 'false';
        $timeout = $extension->call_timeout ?? 30;
        $desc    = htmlspecialchars($extension->description ?? '', ENT_XML1);

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<!-- NEXVARY CX Cloud — Extension {$number} @ {$dom} | Generated: {$this->timestamp()} -->
<include>
  <user id="{$number}">
    <params>
      <param name="password" value="{$password}"/>
      <param name="vm-password" value="{$vmPin}"/>
    </params>
    <variables>
      <variable name="toll_allow" value="domestic,international,local"/>
      <variable name="accountcode" value="{$number}"/>
      <variable name="user_context" value="{$dom}"/>
      <variable name="domain_name" value="{$dom}"/>
      <variable name="effective_caller_id_name" value="{$desc}"/>
      <variable name="effective_caller_id_number" value="{$number}"/>
      <variable name="outbound_caller_id_name" value="{$desc}"/>
      <variable name="outbound_caller_id_number" value="{$number}"/>
      <variable name="limit_max" value="5"/>
      <variable name="limit_destination" value="error/user_busy"/>
      <variable name="missed_call_app" value="email"/>
      <variable name="missed_call_data" value=""/>
      <variable name="transfer_fallback_extension" value="operator"/>
      <variable name="do_not_disturb" value="{$dnd}"/>
      <variable name="call_forward_all" value="{$fwd}"/>
      <variable name="call_forward_all_enabled" value="{$fwdEnabled}"/>
      <variable name="call_timeout" value="{$timeout}"/>
      <variable name="sip_force_expires" value="60"/>
      <variable name="user_record" value="none"/>
    </variables>
  </user>
</include>
XML;
    }

    private function renderGatewayXml(Trunk $trunk, string $password = ''): string
    {
        $name         = htmlspecialchars("nexvary-{$trunk->id}", ENT_XML1);
        $host         = htmlspecialchars($trunk->sip_host, ENT_XML1);
        $user         = htmlspecialchars($trunk->username ?? '', ENT_XML1);
        $pass         = htmlspecialchars($password ?: ($trunk->getRawOriginal('password') ?? ''), ENT_XML1);
        $realm        = htmlspecialchars($trunk->realm ?? $trunk->sip_host, ENT_XML1);
        $transport    = $trunk->transport ?? 'udp';
        $register     = $trunk->register ? 'true' : 'false';
        $callerIdName = htmlspecialchars($trunk->caller_id_name ?? 'NEXVARY CX', ENT_XML1);
        $callerIdNum  = htmlspecialchars($trunk->caller_id_number ?? '', ENT_XML1);

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<!-- NEXVARY CX Cloud — Trunk {$trunk->id} ({$trunk->name}) | Generated: {$this->timestamp()} -->
<include>
  <gateway name="{$name}">
    <param name="username" value="{$user}"/>
    <param name="realm" value="{$realm}"/>
    <param name="from-domain" value="{$realm}"/>
    <param name="password" value="{$pass}"/>
    <param name="proxy" value="{$host}"/>
    <param name="register-proxy" value="{$host}"/>
    <param name="register" value="{$register}"/>
    <param name="contact-params" value="transport={$transport}"/>
    <param name="caller-id-in-from" value="false"/>
    <param name="outbound-caller-id-name" value="{$callerIdName}"/>
    <param name="outbound-caller-id-number" value="{$callerIdNum}"/>
    <param name="retry-seconds" value="30"/>
    <param name="expire-seconds" value="3600"/>
    <param name="ping" value="25"/>
    <param name="ping-max" value="0"/>
    <param name="ping-min" value="1"/>
  </gateway>
</include>
XML;
    }

    private function renderInboundRouteXml(array $route): string
    {
        $did    = htmlspecialchars($route['did'] ?? '', ENT_XML1);
        $dest   = htmlspecialchars($route['destination'] ?? '', ENT_XML1);
        $domain = htmlspecialchars($route['domain'] ?? 'default', ENT_XML1);
        $id     = $route['id'];

        // Transfer into the tenant's domain context, not the global "default".
        // This is the key FusionPBX pattern for multi-tenant inbound routing.
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<!-- NEXVARY CX Cloud — Inbound Route {$id} → {$domain} | Generated: {$this->timestamp()} -->
<include>
  <extension name="nexvary-inbound-{$id}">
    <condition field="destination_number" expression="^{$did}$">
      <action application="set" data="domain_name={$domain}"/>
      <action application="set" data="tenant_domain={$domain}"/>
      <action application="transfer" data="{$dest} XML {$domain}"/>
    </condition>
  </extension>
</include>
XML;
    }

    private function renderOutboundRouteXml(array $route): string
    {
        $rawPattern = $route['pattern'] ?? '.*';
        $pattern    = htmlspecialchars($rawPattern, ENT_XML1);
        $gateway    = htmlspecialchars($route['gateway'] ?? '', ENT_XML1);
        $prefix     = htmlspecialchars($route['prefix'] ?? '', ENT_XML1);
        $id         = $route['id'];
        $domain     = htmlspecialchars($route['domain'] ?? 'default', ENT_XML1);

        // If the dial pattern contains a capture group, FreeSWITCH will set $1
        // to the matched portion (e.g. the number after stripping a prefix).
        // If there is no capture group, $1 is undefined — use ${destination_number}
        // so the full dialed number is passed to the gateway instead of nothing.
        $dialTarget = str_contains($rawPattern, '(') ? '\$1' : '\${destination_number}';

        // The outbound route lives in dialplan/{domain}/ so it is only reachable
        // from extensions authenticated in the same domain context.
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<!-- NEXVARY CX Cloud — Outbound Route {$id} @ {$domain} | Generated: {$this->timestamp()} -->
<include>
  <extension name="nexvary-outbound-{$id}">
    <condition field="destination_number" expression="^{$pattern}$">
      <action application="set" data="effective_caller_id_number=\${caller_id_number}"/>
      <action application="bridge" data="sofia/gateway/{$gateway}/{$prefix}{$dialTarget}"/>
    </condition>
  </extension>
</include>
XML;
    }


    // ── SIP Profiles ─────────────────────────────────────────────────────────

    public function writeSipProfileConfig(SipProfile $profile): void
    {
        $dir  = "{$this->confDir}/sip_profiles";
        $this->ensureDir($dir);
        $xml  = $this->renderSipProfileXml($profile);
        $name = "nexvary-{$profile->type}-{$profile->id}";
        $path = "{$dir}/{$name}.xml";
        File::put($path, $xml);
        Log::info("FreeSWITCH SIP profile written: {$path}");
    }

    public function deleteSipProfileConfig(SipProfile $profile): void
    {
        $name = "nexvary-{$profile->type}-{$profile->id}";
        $path = "{$this->confDir}/sip_profiles/{$name}.xml";
        if (File::exists($path)) {
            File::delete($path);
            Log::info("FreeSWITCH SIP profile removed: {$path}");
        }
    }

    private function renderSipProfileXml(SipProfile $profile): string
    {
        $id      = $profile->id;
        $type    = htmlspecialchars($profile->type, ENT_XML1);
        $name    = "nexvary-{$type}-{$id}";
        $ts      = $this->timestamp();
        $port    = $profile->sip_port ?? ($profile->type === 'internal' ? 5060 : 5080);
        $sipIp   = htmlspecialchars($profile->sip_ip   ?? 'auto', ENT_XML1);
        $rtpIp   = htmlspecialchars($profile->rtp_ip   ?? 'auto', ENT_XML1);
        $extSip  = htmlspecialchars($profile->ext_sip_ip ?? $sipIp, ENT_XML1);
        $extRtp  = htmlspecialchars($profile->ext_rtp_ip ?? $rtpIp, ENT_XML1);
        $rtpTo   = $profile->rtp_timeout      ?? 300;
        $rtpHold = $profile->rtp_hold_timeout ?? 1800;
        $acl     = htmlspecialchars($profile->apply_inbound_acl ?? '', ENT_XML1);

        // Codec prefs
        $codecs     = $profile->codecs ?? SipProfile::defaultCodecs();
        $codecPrefs = htmlspecialchars(implode(',', $codecs), ENT_XML1);

        // NAT
        $natEnabled = $profile->nat_enabled            ? 'true' : 'false';
        $aggNat     = $profile->aggressive_nat_detection ? 'true' : 'false';

        // TLS
        $tlsEnabled = $profile->tls_enabled ? 'true' : 'false';
        $tlsPort    = $profile->tls_port    ?? ($port + 1);
        $srtpMode   = htmlspecialchars($profile->srtp_mode ?? 'disabled', ENT_XML1);

        $acl_line     = $acl     ? "
    <param name=\"apply-inbound-acl\" value=\"{$acl}\"/>"     : '';
        $tls_block    = $profile->tls_enabled ? "
    <param name=\"tls\" value=\"true\"/>
    <param name=\"tls-sip-port\" value=\"{$tlsPort}\"/>
    <param name=\"tls-cert-dir\" value=\"/etc/freeswitch/tls\"/>
    <param name=\"tls-version\" value=\"tlsv1.2,tlsv1.3\"/>" : '';
        $srtp_line    = $srtpMode !== 'disabled' ? "
    <param name=\"rtp-secure-media\" value=\"{$srtpMode}\"/>" : '';

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<!-- NEXVARY CX Cloud — SIP Profile: {$name} | Generated: {$ts} -->
<include>
  <profile name="{$name}">
    <settings>
      <param name="sip-port" value="{$port}"/>
      <param name="sip-ip" value="{$sipIp}"/>
      <param name="rtp-ip" value="{$rtpIp}"/>
      <param name="ext-sip-ip" value="{$extSip}"/>
      <param name="ext-rtp-ip" value="{$extRtp}"/>
      <param name="aggressive-nat-detection" value="{$aggNat}"/>
      <param name="NDLB-force-rport" value="{$natEnabled}"/>
      <param name="rtp-timeout-sec" value="{$rtpTo}"/>
      <param name="rtp-hold-timeout-sec" value="{$rtpHold}"/>
      <param name="inbound-codec-prefs" value="{$codecPrefs}"/>
      <param name="outbound-codec-prefs" value="{$codecPrefs}"/>{$acl_line}{$tls_block}{$srtp_line}
    </settings>
  </profile>
</include>
XML;
    }

    private function renderIvrMenuXml(array $menu): string
    {
        $name       = htmlspecialchars("nexvary-ivr-{$menu['id']}", ENT_XML1);
        $greeting   = htmlspecialchars($menu['greeting_file'] ?? 'ivr/ivr-enter_ext.wav', ENT_XML1);
        $timeout    = $menu['timeout'] ?? 10000;
        $maxRetries = $menu['max_retries'] ?? 3;

        $entries = '';
        foreach ($menu['entries'] ?? [] as $entry) {
            $digit  = htmlspecialchars($entry['digit'], ENT_XML1);
            $action = htmlspecialchars($entry['action'], ENT_XML1);
            $param  = htmlspecialchars($entry['param'] ?? '', ENT_XML1);
            $entries .= "    <entry action=\"{$action}\" digits=\"{$digit}\" param=\"{$param}\"/>\n";
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<!-- NEXVARY CX Cloud — IVR Menu {$menu['id']} | Generated: {$this->timestamp()} -->
<include>
  <menu name="{$name}"
        greet-long="{$greeting}"
        greet-short="{$greeting}"
        invalid-sound="ivr/ivr-that_was_an_invalid_entry.wav"
        exit-sound="voicemail/vm-goodbye.wav"
        timeout="{$timeout}"
        max-failures="{$maxRetries}"
        max-timeouts="{$maxRetries}">
{$entries}
  </menu>
</include>
XML;
    }

    /**
     * Write an arbitrary dialplan file into the tenant domain context.
     */
    public function writeDialplanFile(string $domain, string $filename, string $xml): void
    {
        $dir = "{$this->confDir}/dialplan/{$domain}";
        $this->ensureDir($dir);
        File::put("{$dir}/{$filename}", $xml);
    }

    /**
     * Remove an arbitrary dialplan file from the tenant domain context.
     */
    public function removeDialplanFile(string $domain, string $filename): void
    {
        $path = "{$this->confDir}/dialplan/{$domain}/{$filename}";
        if (File::exists($path)) {
            File::delete($path);
        }
    }

    private function ensureDir(string $dir): void
    {
        if (!File::isDirectory($dir)) {
            // 0770: group nexvary-pbx (GID 2000) shared between the app user (uid 1001)
            // and the FreeSWITCH container (root). Setgid on the parent directory
            // (set by docker-entrypoint.sh) propagates the group to new subdirs.
            File::makeDirectory($dir, 0770, true);
        }
    }

    private function timestamp(): string
    {
        return now()->toDateTimeString();
    }
}
