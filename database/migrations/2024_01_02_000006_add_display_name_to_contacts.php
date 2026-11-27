<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            // CRM controller and Contact model use a simplified "name" field
            // while the original migration has first_name/last_name. Add the
            // CRM-facing columns so both paths work without conflict.
            $table->string('name')->nullable()->after('type');
            $table->string('company')->nullable()->after('phone');
            $table->boolean('enabled')->default(true)->after('company');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['name', 'company', 'enabled']);
        });
    }
};
