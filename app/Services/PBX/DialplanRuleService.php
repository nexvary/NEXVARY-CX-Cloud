<?php
namespace App\Services\PBX;

use App\Models\DialplanRule;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Renders DialplanRule records to FreeSWITCH dialplan XML.
 * Written to: dialplan/{domain}/nexvary-rule-{id}.xml
 */
class DialplanRuleService
{
    public function __construct(
        private readonly string $confDir,
        private readonly FreeSwitchService $fs,
    ) {}

    public static function make(): static
    {
        return new static(
            confDir: config('nexvary.freeswitch.conf_dir', '/etc/freeswitch'),
            fs: FreeSwitchService::make(),
        );
    }

    public function create(array $data, string $domain): DialplanRule
    {
        $rule = DialplanRule::create($data);
        $this->write($rule, $domain);
        $this->reloadDialplan();
        return $rule->fresh();
    }

    public function update(DialplanRule $rule, array $data, string $domain): DialplanRule
    {
        $this->deleteFile($rule, $domain);
        $rule->update($data);
        $this->write($rule->fresh(), $domain);
        $this->reloadDialplan();
        return $rule->fresh();
    }

    public function delete(DialplanRule $rule, string $domain): void
    {
        $this->deleteFile($rule, $domain);
        $rule->delete();
        $this->reloadDialplan();
    }

    public function write(DialplanRule $rule, string $domain): void
    {
        $dir = "{$this->confDir}/dialplan/{$domain}";
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0770, true);
        }
        $xml  = $this->render($rule, $domain);
        $path = "{$this->confDir}/dialplan/{$domain}/nexvary-rule-{$rule->id}.xml";
        File::put($path, $xml);
        Log::info("FreeSWITCH dialplan rule written: {$path}");
    }

    public function deleteFile(DialplanRule $rule, string $domain): void
    {
        $path = "{$this->confDir}/dialplan/{$domain}/nexvary-rule-{$rule->id}.xml";
        if (File::exists($path)) {
            File::delete($path);
        }
    }

    public function render(DialplanRule $rule, string $domain): string
    {
        $id       = $rule->id;
        $name     = htmlspecialchars($rule->name, ENT_XML1);
        $continue = $rule->continue ? 'true' : 'false';
        $ts       = now()->toDateTimeString();
        $conds    = $this->buildConditions($rule);
        return implode("\n", [
            '<?xml version="1.0" encoding="UTF-8" standalone="no"?>',
            "<!-- NEXVARY CX Cloud -- Dialplan Rule: {$name} | Generated: {$ts} -->",
            '<include>',
            "  <extension name=\"nexvary-rule-{$id}\" continue=\"{$continue}\">",
            $conds,
            '  </extension>',
            '</include>',
        ]);
    }

    private function buildConditions(DialplanRule $rule): string
    {
        $out = '';
        foreach ($rule->conditions as $cond) {
            $field = htmlspecialchars($cond['field']       ?? 'destination_number', ENT_XML1);
            $expr  = htmlspecialchars($cond['expression']  ?? '.*',                 ENT_XML1);
            $brk   = htmlspecialchars($cond['break']       ?? 'on-false',           ENT_XML1);
            $out  .= "    <condition field=\"{$field}\" expression=\"{$expr}\" break=\"{$brk}\">\n";
            $out  .= $this->buildActions($rule->actions ?? []);
            $out  .= "    </condition>\n";
        }
        return rtrim($out);
    }

    private function buildActions(array $actions): string
    {
        $out = '';
        foreach ($actions as $action) {
            $app  = htmlspecialchars($action['application'] ?? '', ENT_XML1);
            $data = htmlspecialchars($action['data']        ?? '', ENT_XML1);
            $tag  = !empty($action['anti_action']) ? 'anti-action' : 'action';
            $out .= "      <{$tag} application=\"{$app}\" data=\"{$data}\"/>\n";
        }
        return $out;
    }

    private function reloadDialplan(): void
    {
        if ($this->fs->connect()) {
            $this->fs->reloadDialplan();
            $this->fs->disconnect();
        }
    }
}
