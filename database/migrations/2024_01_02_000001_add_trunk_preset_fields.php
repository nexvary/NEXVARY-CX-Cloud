<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trunks', function (Blueprint $table) {
            $table->string('auth_mode', 20)->default('credential')->after('transport');
            $table->string('proxy')->nullable()->after('sip_port');
            $table->boolean('caller_id_in_from')->default(true)->after('caller_id_number');
            $table->string('preset_key', 40)->nullable()->after('provider_meta');
            $table->string('preset_version', 20)->nullable()->after('preset_key');
        });
    }

    public function down(): void
    {
        Schema::table('trunks', function (Blueprint $table) {
            $table->dropColumn(['auth_mode', 'proxy', 'caller_id_in_from', 'preset_key', 'preset_version']);
        });
    }
};
