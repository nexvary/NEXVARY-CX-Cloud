<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('call_agents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('extension_id')->nullable();
            $table->string('name', 128);
            $table->enum('status', ['available', 'unavailable', 'on_break'])->default('available');
            $table->unsignedTinyInteger('skill_level')->default(1);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('extension_id')->references('id')->on('extensions')->nullOnDelete();
            $table->index(['tenant_id', 'status']);
        });

        // Pivot: queue_agents already exists? Add if not.
        if (! Schema::hasTable('queue_agents')) {
            Schema::create('queue_agents', function (Blueprint $table) {
                $table->id();
                $table->uuid('call_queue_id');
                $table->uuid('call_agent_id');
                $table->enum('status', ['logged_in', 'logged_out'])->default('logged_in');
                $table->unsignedTinyInteger('penalty')->default(0);
                $table->timestamps();

                $table->foreign('call_queue_id')->references('id')->on('call_queues')->cascadeOnDelete();
                $table->foreign('call_agent_id')->references('id')->on('call_agents')->cascadeOnDelete();
                $table->unique(['call_queue_id', 'call_agent_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_agents');
        Schema::dropIfExists('call_agents');
    }
};
