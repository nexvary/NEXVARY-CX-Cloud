<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // CallQueueController::store() does not collect an extension number;
        // the FreeSWITCH queue is addressed by name, not by an extension dial.
        // Make the column nullable so queues can be created before an extension
        // is assigned.
        Schema::table('call_queues', function (Blueprint $table) {
            $table->string('extension', 20)->nullable()->change();
        });

        // IvrController and IvrMenu model use 'timeout' and 'max_failures', but
        // the original migration used 'timeout_ms' and 'max_retries'. Add the
        // expected columns so the controller can persist them.
        Schema::table('ivr_menus', function (Blueprint $table) {
            $table->integer('timeout')->nullable()->after('greeting_tts');
            $table->integer('max_failures')->nullable()->after('timeout');
        });
    }

    public function down(): void
    {
        Schema::table('call_queues', function (Blueprint $table) {
            $table->string('extension', 20)->nullable(false)->change();
        });

        Schema::table('ivr_menus', function (Blueprint $table) {
            $table->dropColumn(['timeout', 'max_failures']);
        });
    }
};
