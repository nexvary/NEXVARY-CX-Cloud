<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dids', function (Blueprint $table) {
            // Direct routing destination — avoids a join to inbound_routes for the
            // common case where a DID maps to exactly one fixed destination.
            $table->string('destination_type', 30)->nullable()->after('trunk_id');
            $table->uuid('destination_id')->nullable()->after('destination_type');
        });
    }

    public function down(): void
    {
        Schema::table('dids', function (Blueprint $table) {
            $table->dropColumn(['destination_type', 'destination_id']);
        });
    }
};
