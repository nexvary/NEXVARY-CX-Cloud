<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('dialplan_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable()->index();
            $table->string('name', 100);
            $table->string('context', 100)->nullable();
            $table->integer('priority')->default(100);
            $table->boolean('continue')->default(false);
            $table->json('conditions');
            $table->json('actions');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('dialplan_rules'); }
};
