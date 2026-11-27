<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-tenancy foundation.
 *
 * Adds:
 *   - tenants table (the organizational unit owning resources)
 *   - domains table (web + SIP domains mapped to tenants)
 *   - ring_groups + ring_group_members
 *   - time_conditions
 *   - tenant_id foreign key on all tenant-scoped tables
 *   - Composite unique indexes replacing single-column ones where tenant isolation matters
 *
 * Design decisions:
 *   - tenant_id = null means the record belongs to the system/superadmin
 *   - Extension numbers MUST be unique per (tenant_id, number), not globally
 *   - SIP domains within FreeSWITCH are per-tenant via the domains table
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Tenants ───────────────────────────────────────────────────────────

        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('plan')->default('standard'); // standard, professional, enterprise
            $table->string('status')->default('active'); // active, suspended, cancelled
            $table->string('timezone')->default('UTC');
            $table->string('locale', 10)->default('en');
            $table->json('settings')->nullable();
            $table->integer('max_extensions')->default(50);
            $table->integer('max_trunks')->default(5);
            $table->integer('max_dids')->default(10);
            $table->integer('max_users')->default(20);
            $table->string('billing_email')->nullable();
            $table->uuid('primary_domain_id')->nullable(); // set after domains created
            $table->timestamps();
            $table->softDeletes();
        });

        // ── Domains ───────────────────────────────────────────────────────────

        Schema::create('domains', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name')->unique(); // e.g. company.nexvary.cloud
            $table->string('type')->default('sip'); // sip, web, both
            $table->boolean('verified')->default(false);
            $table->string('verification_token')->nullable();
            $table->string('certificate_path')->nullable();
            $table->timestamp('certificate_expires_at')->nullable();
            $table->boolean('tls_enabled')->default(false);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // ── Ring Groups ───────────────────────────────────────────────────────

        Schema::create('ring_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('name');
            $table->string('extension', 20);
            $table->string('strategy')->default('simultaneous'); // simultaneous, sequential, ordered
            $table->integer('timeout')->default(30);
            $table->string('destination_type')->nullable(); // voicemail, ivr, extension
            $table->string('destination')->nullable();
            $table->string('caller_id_policy')->default('original'); // original, group, anonymous
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'extension']);
        });

        Schema::create('ring_group_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ring_group_id')->constrained()->cascadeOnDelete();
            $table->string('number', 30); // extension number or external number
            $table->integer('timeout')->default(30);
            $table->integer('priority')->default(0);
            $table->timestamps();
        });

        // ── Time Conditions ───────────────────────────────────────────────────

        Schema::create('time_conditions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('name');
            $table->string('timezone')->default('UTC');
            $table->json('schedules'); // array of {wday_start, wday_end, hour_start, hour_end, mday_start, mday_end, mon_start, mon_end}
            $table->json('holidays')->nullable(); // array of {date, name}
            $table->string('match_destination_type')->nullable();
            $table->string('match_destination')->nullable();
            $table->string('nomatch_destination_type')->nullable();
            $table->string('nomatch_destination')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // ── Add tenant_id to all tenant-scoped tables ─────────────────────────

        $tenantedTables = [
            'users',
            'extensions',
            'trunks',
            'call_records',
            'inbound_routes',
            'outbound_routes',
            'ivr_menus',
            'call_queues',
            'dids',
            'contacts',
            'tickets',
            'billing_accounts',
            'sms_conversations',
        ];

        foreach ($tenantedTables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->foreignUuid('tenant_id')->nullable()->after('id')
                      ->constrained('tenants')->nullOnDelete();
                $table->index('tenant_id');
            });
        }

        // ── Fix unique constraints that need tenant-scoping ───────────────────

        // extensions.number was globally unique; must now be unique per (tenant_id, number)
        Schema::table('extensions', function (Blueprint $table) {
            $table->dropUnique(['number']);
            $table->unique(['tenant_id', 'number']);
        });

        // call_queues.extension was globally unique; must now be unique per (tenant_id, extension)
        Schema::table('call_queues', function (Blueprint $table) {
            $table->dropUnique(['extension']);
            $table->unique(['tenant_id', 'extension']);
        });
    }

    public function down(): void
    {
        // Restore original unique constraints
        Schema::table('call_queues', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'extension']);
            $table->unique(['extension']);
        });
        Schema::table('extensions', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'number']);
            $table->unique(['number']);
        });

        $tenantedTables = [
            'sms_conversations', 'billing_accounts', 'tickets', 'contacts', 'dids',
            'call_queues', 'ivr_menus', 'outbound_routes', 'inbound_routes',
            'call_records', 'trunks', 'extensions', 'users',
        ];

        foreach ($tenantedTables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['tenant_id']);
                $table->dropColumn('tenant_id');
            });
        }

        Schema::dropIfExists('time_conditions');
        Schema::dropIfExists('ring_group_members');
        Schema::dropIfExists('ring_groups');
        Schema::dropIfExists('domains');
        Schema::dropIfExists('tenants');
    }
};
