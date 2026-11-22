<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Users & Auth ──────────────────────────────────────────────────────

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role')->default('agent'); // superadmin, admin, supervisor, agent
            $table->string('locale', 10)->default('ar');
            $table->string('timezone')->default('UTC');
            $table->boolean('mfa_enabled')->default(false);
            $table->string('mfa_secret')->nullable();
            $table->boolean('active')->default(true);
            $table->string('extension_number', 20)->nullable();
            $table->string('department')->nullable();
            $table->string('avatar')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('model_type')->nullable();
            $table->uuid('model_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            $table->index(['model_type', 'model_id']);
            $table->index('created_at');
        });

        // ── Organization ──────────────────────────────────────────────────────

        Schema::create('organization_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string'); // string, boolean, integer, json
            $table->string('group')->default('general');
            $table->timestamps();
        });

        // ── Extensions ────────────────────────────────────────────────────────

        Schema::create('extensions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number', 20)->unique();
            $table->string('description')->nullable();
            $table->string('sip_password'); // hashed
            $table->integer('voicemail_pin')->nullable();
            $table->integer('call_timeout')->default(30);
            $table->boolean('forward_enabled')->default(false);
            $table->string('forward_destination', 50)->nullable();
            $table->boolean('dnd_enabled')->default(false);
            $table->boolean('voicemail_enabled')->default(true);
            $table->boolean('call_waiting')->default(true);
            $table->boolean('enabled')->default(true);
            $table->string('mac_address', 17)->nullable(); // for auto-provisioning
            $table->string('device_type')->nullable();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // ── SIP Trunks ────────────────────────────────────────────────────────

        Schema::create('trunks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('provider')->default('generic'); // telnyx, generic, etc.
            $table->string('sip_host');
            $table->integer('sip_port')->default(5060);
            $table->string('transport', 10)->default('udp'); // udp, tcp, tls
            $table->string('username')->nullable();
            $table->string('password')->nullable(); // encrypted
            $table->string('realm')->nullable();
            $table->string('caller_id_name')->nullable();
            $table->string('caller_id_number', 30)->nullable();
            $table->boolean('register')->default(true);
            $table->integer('priority')->default(10);
            $table->boolean('enabled')->default(true);
            $table->json('codecs')->nullable();
            $table->json('provider_meta')->nullable(); // provider-specific config
            $table->timestamps();
            $table->softDeletes();
        });

        // ── Call Records ──────────────────────────────────────────────────────

        Schema::create('call_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('uuid')->unique(); // FreeSWITCH call UUID
            $table->string('direction'); // inbound, outbound, internal
            $table->string('caller_id_name')->nullable();
            $table->string('caller_id_number', 30);
            $table->string('destination_number', 30);
            $table->string('context', 64)->default('default');
            $table->foreignUuid('trunk_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('extension_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('billing_account_id')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration_seconds')->default(0); // total
            $table->integer('billable_seconds')->default(0); // after answer
            $table->string('hangup_cause', 50)->nullable();
            $table->boolean('billable')->default(true);
            $table->boolean('rated')->default(false);
            $table->timestamp('rated_at')->nullable();
            $table->string('recording_path')->nullable();
            $table->integer('recording_size')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index('started_at');
            $table->index(['caller_id_number', 'started_at']);
            $table->index(['destination_number', 'started_at']);
        });

        // ── Inbound & Outbound Routes ─────────────────────────────────────────

        Schema::create('inbound_routes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('did', 30)->nullable(); // matched DID or null=default
            $table->string('name');
            $table->string('destination_type'); // extension, ivr, queue, voicemail
            $table->string('destination');
            $table->foreignUuid('trunk_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('priority')->default(10);
            $table->boolean('enabled')->default(true);
            $table->json('time_conditions')->nullable();
            $table->timestamps();
        });

        Schema::create('outbound_routes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('dial_pattern', 100);
            $table->foreignUuid('trunk_id')->nullable()->constrained()->nullOnDelete();
            $table->string('trunk_prefix', 20)->nullable();
            $table->integer('priority')->default(10);
            $table->boolean('enabled')->default(true);
            $table->json('allowed_roles')->nullable();
            $table->timestamps();
        });

        // ── IVR ───────────────────────────────────────────────────────────────

        Schema::create('ivr_menus', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('greeting_type')->default('file'); // file, tts
            $table->string('greeting_file')->nullable();
            $table->text('greeting_tts')->nullable();
            $table->integer('timeout_ms')->default(10000);
            $table->integer('max_retries')->default(3);
            $table->string('invalid_sound')->nullable();
            $table->string('exit_sound')->nullable();
            $table->string('timeout_destination')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('ivr_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ivr_menu_id')->constrained()->cascadeOnDelete();
            $table->string('digit', 2);
            $table->string('action'); // transfer, ivr, voicemail, hangup
            $table->string('param')->nullable();
            $table->timestamps();
        });

        // ── Call Queues ───────────────────────────────────────────────────────

        Schema::create('call_queues', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('extension', 20)->unique();
            $table->string('strategy')->default('ring-all'); // ring-all, round-robin, least-calls
            $table->integer('timeout')->default(60);
            $table->integer('max_wait_time')->default(300);
            $table->string('moh_sound')->nullable();
            $table->string('queue_moh_sound')->nullable();
            $table->string('timeout_action')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('queue_agents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('queue_id')->constrained('call_queues')->cascadeOnDelete();
            $table->foreignUuid('extension_id')->constrained()->cascadeOnDelete();
            $table->integer('priority')->default(0);
            $table->string('status')->default('logged-out'); // logged-in, logged-out, on-break
            $table->timestamps();
        });

        // ── DIDs ──────────────────────────────────────────────────────────────

        Schema::create('dids', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number', 30)->unique();
            $table->string('description')->nullable();
            $table->foreignUuid('trunk_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('inbound_route_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('sms_enabled')->default(false);
            $table->boolean('voice_enabled')->default(true);
            $table->boolean('enabled')->default(true);
            $table->json('provider_meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dids');
        Schema::dropIfExists('queue_agents');
        Schema::dropIfExists('call_queues');
        Schema::dropIfExists('ivr_entries');
        Schema::dropIfExists('ivr_menus');
        Schema::dropIfExists('outbound_routes');
        Schema::dropIfExists('inbound_routes');
        Schema::dropIfExists('call_records');
        Schema::dropIfExists('trunks');
        Schema::dropIfExists('extensions');
        Schema::dropIfExists('organization_settings');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
