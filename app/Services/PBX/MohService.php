<?php

namespace App\Services\PBX;

use App\Models\MohClass;
use Illuminate\Support\Collection;

class MohService
{
    public function list(string $tenantId): Collection
    {
        return MohClass::forTenant($tenantId)->orderBy('name')->get();
    }

    public function create(array $data): MohClass
    {
        return MohClass::create($data);
    }

    public function update(MohClass $moh, array $data): MohClass
    {
        $moh->update($data);
        return $moh;
    }

    public function delete(MohClass $moh): void
    {
        $moh->delete();
    }

    public function renderConfig(string $tenantId): string
    {
        $classes = $this->list($tenantId)->where('enabled', true);
        $xml     = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration name=\"local_stream.conf\" description=\"Music on Hold\">\n  <streams>\n";

        foreach ($classes as $class) {
            $name = htmlspecialchars($class->name, ENT_XML1 | ENT_QUOTES);
            if ($class->source === 'stream_url' && $class->stream_url) {
                $url = htmlspecialchars($class->stream_url, ENT_XML1 | ENT_QUOTES);
                $xml .= "    <stream name=\"{$name}\" path=\"shout://{$url}\" shuffle=\"false\"/>\n";
            } else {
                $shuffle = $class->shuffle ? 'true' : 'false';
                $xml    .= "    <stream name=\"{$name}\" path=\"/var/lib/freeswitch/sounds/music/{$name}\" shuffle=\"{$shuffle}\"/>\n";
            }
        }

        $xml .= "  </streams>\n</configuration>\n";
        return $xml;
    }
}
