<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('business_hours', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name', 128);
            $table->json('schedule');
            $table->json('holidays')->nullable();
            $table->string('timezone', 64)->default('UTC');
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
        });

        Schema::create('day_night_modes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name', 128);
            $table->enum('mode', ['day', 'night'])->default('day');
            $table->string('day_destination', 128)->nullable();
            $table->string('night_destination', 128)->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('day_night_modes');
        Schema::dropIfExists('business_hours');
    }
};
