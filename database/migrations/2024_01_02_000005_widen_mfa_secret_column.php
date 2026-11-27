<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // AES-256-GCM encrypted TOTP secrets produce a base64 JSON envelope that
            // exceeds varchar(255). Text is unbounded and appropriate for ciphertext.
            $table->text('mfa_secret')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('mfa_secret')->nullable()->change();
        });
    }
};
