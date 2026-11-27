<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('device_provisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable()->index();
            $table->uuid('extension_id')->nullable()->index();
            $table->string('mac_address', 20)->nullable();
            $table->string('device_type', 30)->default('generic');
            $table->string('provision_token_hash', 64)->unique();
            $table->boolean('enabled')->default(true);
            $table->json('settings')->nullable();
            $table->timestamp('last_provisioned_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'mac_address']);
        });
    }
    public function down(): void { Schema::dropIfExists('device_provisions'); }
};
