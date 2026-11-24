<?php

namespace App\Console\Commands\PBX;

use App\Models\Domain;
use App\Models\Extension;
use App\Models\Tenant;
use App\Services\PBX\FusionPbxConfigService;
use App\Services\PBX\FreeSwitchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sets up isolated test tenants and SIP extensions for CI integration testing.
 *
 * Creates two tenants with deterministic SIP domains and extensions so that
 * CI can verify multi-tenant isolation, intra-tenant calling, and routing.
 * Outputs credentials in a format that CI scripts can consume.
 *
 * MUST NOT be run in production. Protected by APP_ENV=testing guard.
 */
class CiSipSetup extends Command
{
    protected $signature = 'nexvary:pbx:ci-setup
                            {--password= : SIP password to assign (default: random)}
                            {--domain-a=ci-tenant-a.pbx.test : SIP domain for tenant A}
                            {--domain-b=ci-tenant-b.pbx.test : SIP domain for tenant B}
                            {--reload : Reload FreeSWITCH dialplan after setup}';

    protected $description = 'CI only: create test tenants + SIP extensions for integration tests';

    public function __construct(
        private readonly FusionPbxConfigService $config,
        private readonly FreeSwitchService      $fs,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! in_array(app()->environment(), ['testing', 'local', 'ci'], true)) {
            $this->error('This command must not run in production (APP_ENV=' . app()->environment() . ')');
            return self::FAILURE;
        }

        $password  = $this->option('password') ?: Str::random(20);
        $domainA   = $this->option('domain-a');
        $domainB   = $this->option('domain-b');

        $this->info("Setting up CI SIP tenants...");

        DB::transaction(function () use ($password, $domainA, $domainB) {
            foreach ([
                ['domain' => $domainA, 'slug' => 'ci-tenant-a', 'exts' => ['1001', '1002']],
                ['domain' => $domainB, 'slug' => 'ci-tenant-b', 'exts' => ['1001', '1002']],
            ] as $cfg) {
                $tenant = Tenant::withoutGlobalScopes()
                    ->where('slug', $cfg['slug'])->first();

                if (! $tenant) {
                    $tenant = Tenant::create([
                        'name'            => 'CI Tenant ' . strtoupper(last(explode('-', $cfg['slug']))),
                        'slug'            => $cfg['slug'],
                        'plan'            => 'trial',
                        'status'          => 'active',
                        'max_extensions'  => 10,
                        'max_trunks'      => 2,
                        'max_dids'        => 2,
                        'max_users'       => 5,
                    ]);
                }

                $domain = Domain::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->where('name', $cfg['domain'])->first();

                if (! $domain) {
                    $domain = Domain::create([
                        'tenant_id' => $tenant->id,
                        'name'      => $cfg['domain'],
                        'type'      => 'sip',
                        'enabled'   => true,
                        'verified'  => true,
                    ]);
                    $tenant->update(['primary_domain_id' => $domain->id]);
                }

                $this->config->ensureDomainDialplanDir($cfg['domain']);

                foreach ($cfg['exts'] as $extNum) {
                    $ext = Extension::withoutGlobalScopes()
                        ->where('tenant_id', $tenant->id)
                        ->where('number', $extNum)->first();

                    if (! $ext) {
                        $ext = Extension::withoutGlobalScopes()->create([
                            'id'           => Str::uuid(),
                            'tenant_id'    => $tenant->id,
                            'number'       => $extNum,
                            'description'  => "CI ext {$extNum} in {$cfg['domain']}",
                            'sip_password' => hash('sha256', $password),
                            'voicemail_pin'=> 1234,
                            'call_timeout' => 30,
                            'enabled'      => true,
                        ]);
                    }

                    $this->config->writeExtensionConfig($ext, $password, $cfg['domain']);
                    $this->line("  Wrote extension {$extNum}@{$cfg['domain']}");
                }
            }
        });

        if ($this->option('reload')) {
            $this->info("Reloading FreeSWITCH dialplan and directory...");
            $this->fs->reloadDialplan();
            // Also reload the directory so new extensions are registerable
            $this->fs->api('reloadxml');
        }

        // Output machine-readable credentials for CI scripts
        $this->line('');
        $this->line('CI_SIP_DOMAIN_A=' . $domainA);
        $this->line('CI_SIP_DOMAIN_B=' . $domainB);
        $this->line('CI_SIP_EXT_1001_PASS=' . $password);
        $this->line('CI_SIP_EXT_1002_PASS=' . $password);
        $this->line('');
        $this->info('Done. Test extensions: 1001, 1002 in each tenant.');

        return self::SUCCESS;
    }
}
