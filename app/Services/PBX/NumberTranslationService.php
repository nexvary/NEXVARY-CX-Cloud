<?php

namespace App\Services\PBX;

use App\Models\NumberTranslation;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Manages FreeSWITCH number translation dialplan rules.
 * Rules written to dialplan/{domain}/nexvary-translate-{id}.xml
 * with continue=true so they transform the number without halting dialplan.
 */
class NumberTranslationService
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

    public function create(array $data, string $domain): NumberTranslation
    {
        $rule = NumberTranslation::create($data);
        $this->write($rule, $domain);
        $this->reloadDialplan();
        return $rule->fresh();
    }

    public function update(NumberTranslation $rule, array $data, string $domain): NumberTranslation
    {
        $this->deleteFile($rule, $domain);
        $rule->update($data);
        $this->write($rule->fresh(), $domain);
        $this->reloadDialplan();
        return $rule->fresh();
    }

    public function delete(NumberTranslation $rule, string $domain): void
    {
        $this->deleteFile($rule, $domain);
        $rule->delete();
        $this->reloadDialplan();
    }

    public function write(NumberTranslation $rule, string $domain): void
    {
        $dir = "{$this->confDir}/dialplan/{$domain}";
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0770, true);
        }
        $xml  = $this->render($rule, $domain);
        $path = "{$dir}/nexvary-translate-{$rule->id}.xml";
        File::put($path, $xml);
        Log::info("FreeSWITCH number translation written: {$path}");
    }

    public function deleteFile(NumberTranslation $rule, string $domain): void
    {
        $path = "{$this->confDir}/dialplan/{$domain}/nexvary-translate-{$rule->id}.xml";
        if (File::exists($path)) {
            File::delete($path);
        }
    }

    private function render(NumberTranslation $rule, string $domain): string
    {
        $id      = $rule->id;
        $dom     = htmlspecialchars($domain, ENT_XML1);
        $pattern = htmlspecialchars($rule->pattern, ENT_XML1);
        $name    = htmlspecialchars($rule->name, ENT_XML1);
        $ts      = now()->toDateTimeString();
        $actions = $this->buildActions($rule);

        return implode("\n", [
            '<?xml version="1.0" encoding="UTF-8" standalone="no"?>',
            "<!-- NEXVARY CX Cloud -- Number Translation: {$name} @ {$dom} | Generated: {$ts} -->",
            '<include>',
            "  <extension name=\"nexvary-translate-{$id}\" continue=\"true\">",
            "    <condition field=\"destination_number\" expression=\"^{$pattern}$\">",
            $actions,
            '    </condition>',
            '  </extension>',
            '</include>',
        ]);
    }

    private function buildActions(NumberTranslation $rule): string
    {
        $actions = '';
        $replace = htmlspecialchars($rule->replace ?: '$1', ENT_XML1);
        $pre     = htmlspecialchars($rule->add_prefix ?? '', ENT_XML1);

        if ($rule->strip_prefix || $rule->add_prefix || $rule->replace) {
            $actions .= "      <action application=\"set\" data=\"destination_number={$pre}{$replace}\"/>\n";
        }

        if ($rule->privacy_enabled) {
            $actions .= "      <action application=\"set\" data=\"privacy=yes\"/>\n";
            $actions .= "      <action application=\"set\" data=\"sip_h_Privacy=id\"/>\n";
            $actions .= "      <action application=\"set\" data=\"effective_caller_id_number=anonymous\"/>\n";
            $actions .= "      <action application=\"set\" data=\"effective_caller_id_name=Anonymous\"/>\n";
        }

        if ($rule->caller_id_override) {
            $cid = htmlspecialchars($rule->caller_id_override, ENT_XML1);
            $actions .= "      <action application=\"set\" data=\"effective_caller_id_number={$cid}\"/>\n";
        }

        $actions .= "      <action application=\"set\" data=\"nexvary_translated=true\"/>\n";
        return rtrim($actions);
    }

    private function reloadDialplan(): void
    {
        if ($this->fs->connect()) {
            $this->fs->reloadDialplan();
            $this->fs->disconnect();
        }
    }
}
