<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sip_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable()->index();
            $table->string('name', 100);
            $table->string('type', 20)->default('internal'); // internal | external
            $table->boolean('enabled')->default(true);
            // Core network settings
            $table->integer('sip_port')->nullable();             // null = profile default
            $table->string('sip_ip', 100)->nullable();           // null = auto
            $table->string('rtp_ip', 100)->nullable();
            $table->string('ext_sip_ip', 100)->nullable();       // NAT external SIP
            $table->string('ext_rtp_ip', 100)->nullable();       // NAT external RTP
            $table->boolean('nat_enabled')->default(false);
            $table->integer('rtp_timeout')->default(300);
            $table->integer('rtp_hold_timeout')->default(1800);
            // Codec preferences (ordered array)
            $table->json('codecs')->nullable();                   // ['PCMU','PCMA','G722','opus']
            // TLS/SRTP
            $table->boolean('tls_enabled')->default(false);
            $table->integer('tls_port')->nullable();             // default 5061
            $table->string('srtp_mode', 20)->default('disabled'); // disabled|optional|mandatory
            // ACL / security
            $table->string('apply_inbound_acl', 100)->nullable();
            $table->boolean('aggressive_nat_detection')->default(false);
            $table->json('settings')->nullable();                // extra k/v params
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sip_profiles');
    }
};
