<?php

namespace App\Services\PBX;

use App\Models\Extension;
use App\Models\Trunk;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Writes FreeSWITCH configuration files in the FusionPBX directory layout.
 * All changes are reflected immediately after reloadxml / sofia reload.
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

    public function writeExtensionConfig(Extension $extension, string $sipPassword): void
    {
        $dir = "{$this->confDir}/directory/default";
        $this->ensureDir($dir);

        $xml = $this->renderExtensionXml($extension, $sipPassword);
        $path = "{$dir}/{$extension->number}.xml";

        File::put($path, $xml);
        Log::info("FreeSWITCH extension config written: {$path}");
    }

    public function deleteExtensionConfig(Extension $extension): void
    {
        $path = "{$this->confDir}/directory/default/{$extension->number}.xml";
        if (File::exists($path)) {
            File::delete($path);
        }
    }

    public function updateExtensionVariable(Extension $extension, string $key, string $value): void
    {
        $path = "{$this->confDir}/directory/default/{$extension->number}.xml";
        if (!File::exists($path)) {
            return;
        }

        $xml = File::get($path);
        // Update or insert variable
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

    // ── Trunks / Gateways ─────────────────────────────────────────────────────

    /**
     * @param string $password  Plaintext SIP credential — stored in the FS config file only,
     *                          never persisted to the database.
     */
    public function writeTrunkConfig(Trunk $trunk, string $password = ''): void
    {
        $dir = "{$this->confDir}/sip_profiles/external";
        $this->ensureDir($dir);

        $xml = $this->renderGatewayXml($trunk, $password);
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

    // ── Dialplan ──────────────────────────────────────────────────────────────

    public function writeInboundRoute(array $route): void
    {
        $dir = "{$this->confDir}/dialplan/public";
        $this->ensureDir($dir);

        $xml = $this->renderInboundRouteXml($route);
        $path = "{$dir}/nexvary-inbound-{$route['id']}.xml";
        File::put($path, $xml);
    }

    public function writeOutboundRoute(array $route): void
    {
        $dir = "{$this->confDir}/dialplan/default";
        $this->ensureDir($dir);

        $xml = $this->renderOutboundRouteXml($route);
        $path = "{$dir}/nexvary-outbound-{$route['id']}.xml";
        File::put($path, $xml);
    }

    // ── IVR ───────────────────────────────────────────────────────────────────

    public function writeIvrMenu(array $menu): void
    {
        $dir = "{$this->confDir}/autoload_configs";
        $xml = $this->renderIvrMenuXml($menu);
        $path = "{$dir}/ivr.conf.d/nexvary-ivr-{$menu['id']}.xml";
        $this->ensureDir(dirname($path));
        File::put($path, $xml);
    }

    // ── Templates ─────────────────────────────────────────────────────────────

    private function renderExtensionXml(Extension $extension, string $password): string
    {
        $number = htmlspecialchars($extension->number, ENT_XML1);
        $vmPin  = htmlspecialchars((string) $extension->voicemail_pin, ENT_XML1);
        $dnd    = $extension->dnd_enabled ? 'true' : 'false';
        $fwd    = htmlspecialchars($extension->forward_destination ?? '', ENT_XML1);
        $fwdEnabled = $extension->forward_enabled ? 'true' : 'false';
        $timeout = $extension->call_timeout ?? 30;
        $desc   = htmlspecialchars($extension->description ?? '', ENT_XML1);
        $pwdHash = htmlspecialchars(hash('md5', ":{$extension->number}:{$password}"), ENT_XML1);

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<!-- NEXVARY CX Cloud — Extension {$number} | Generated: {$this->timestamp()} -->
<include>
  <user id="{$number}">
    <params>
      <param name="password" value="{$password}"/>
      <param name="vm-password" value="{$vmPin}"/>
    </params>
    <variables>
      <variable name="toll_allow" value="domestic,international,local"/>
      <variable name="accountcode" value="{$number}"/>
      <variable name="user_context" value="default"/>
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
        $name = htmlspecialchars("nexvary-{$trunk->id}", ENT_XML1);
        $host = htmlspecialchars($trunk->sip_host, ENT_XML1);
        $user = htmlspecialchars($trunk->username ?? '', ENT_XML1);
        // Use the explicitly passed plaintext password; fall back to model value as a last resort
        // (the model hides the password from JSON/toArray but it is readable from PHP)
        $pass = htmlspecialchars($password ?: ($trunk->getRawOriginal('password') ?? ''), ENT_XML1);
        $realm = htmlspecialchars($trunk->realm ?? $trunk->sip_host, ENT_XML1);
        $transport = $trunk->transport ?? 'udp';
        $register = $trunk->register ? 'true' : 'false';
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
        $did = htmlspecialchars($route['did'] ?? '', ENT_XML1);
        $dest = htmlspecialchars($route['destination'] ?? '', ENT_XML1);

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<!-- NEXVARY CX Cloud — Inbound Route {$route['id']} | Generated: {$this->timestamp()} -->
<include>
  <extension name="nexvary-inbound-{$route['id']}">
    <condition field="destination_number" expression="^{$did}$">
      <action application="set" data="domain_name=\$\${domain}"/>
      <action application="transfer" data="{$dest} XML default"/>
    </condition>
  </extension>
</include>
XML;
    }

    private function renderOutboundRouteXml(array $route): string
    {
        $pattern = htmlspecialchars($route['pattern'] ?? '.*', ENT_XML1);
        $gateway = htmlspecialchars($route['gateway'] ?? '', ENT_XML1);

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<!-- NEXVARY CX Cloud — Outbound Route {$route['id']} | Generated: {$this->timestamp()} -->
<include>
  <extension name="nexvary-outbound-{$route['id']}">
    <condition field="destination_number" expression="^{$pattern}$">
      <action application="set" data="effective_caller_id_number=\$\${outbound_caller_id}"/>
      <action application="bridge" data="sofia/gateway/{$gateway}/\$1"/>
    </condition>
  </extension>
</include>
XML;
    }

    private function renderIvrMenuXml(array $menu): string
    {
        $name = htmlspecialchars("nexvary-ivr-{$menu['id']}", ENT_XML1);
        $greeting = htmlspecialchars($menu['greeting_file'] ?? 'ivr/ivr-enter_ext.wav', ENT_XML1);
        $timeout = $menu['timeout'] ?? 10000;
        $maxRetries = $menu['max_retries'] ?? 3;

        $entries = '';
        foreach ($menu['entries'] ?? [] as $entry) {
            $digit = htmlspecialchars($entry['digit'], ENT_XML1);
            $action = htmlspecialchars($entry['action'], ENT_XML1);
            $param = htmlspecialchars($entry['param'] ?? '', ENT_XML1);
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

    private function ensureDir(string $dir): void
    {
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0750, true);
        }
    }

    private function timestamp(): string
    {
        return now()->toDateTimeString();
    }
}
