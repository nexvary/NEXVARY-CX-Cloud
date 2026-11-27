<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('number_translations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable()->index();
            $table->string('name', 100);
            $table->string('direction', 20)->default('inbound'); // inbound|outbound|both
            $table->string('pattern', 200);    // regex applied to destination_number
            $table->string('replace', 200);    // replacement ($1 for capture group)
            $table->string('strip_prefix', 20)->nullable();  // strip leading digits
            $table->string('add_prefix', 20)->nullable();    // prepend digits
            $table->boolean('privacy_enabled')->default(false); // set Privacy: id
            $table->string('caller_id_override', 50)->nullable(); // override CID number
            $table->integer('priority')->default(100);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_translations');
    }
};
