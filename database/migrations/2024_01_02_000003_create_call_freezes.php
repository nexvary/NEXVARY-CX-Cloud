<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_freezes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('scope', 20);        // tenant | gateway
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignUuid('trunk_id')->nullable()->constrained('trunks')->nullOnDelete();
            $table->text('reason');
            $table->uuid('activated_by');
            $table->uuid('deactivated_by')->nullable();
            $table->timestamp('activated_at');
            $table->timestamp('deactivated_at')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_freezes');
    }
};
