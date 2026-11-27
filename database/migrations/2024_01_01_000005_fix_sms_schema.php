<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aligns sms_conversations and sms_messages with model field names.
 *
 * The original migration used our_number/their_number, but application
 * code consistently uses did_number/contact_number. This migration
 * renames the columns to match the models and fixes the unique constraint.
 *
 * Also renames sms_messages.provider_message_id -> telnyx_message_id
 * for provider-specificity, and adds missing fields.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Drop old unique constraint before renaming (uses original column names)
        Schema::table('sms_conversations', function (Blueprint $table) {
            $table->dropUnique('sms_conversations_our_number_their_number_unique');
        });

        Schema::table('sms_conversations', function (Blueprint $table) {
            $table->renameColumn('our_number', 'did_number');
            $table->renameColumn('their_number', 'contact_number');
        });

        // Recreate unique constraint with new column names
        Schema::table('sms_conversations', function (Blueprint $table) {
            $table->unique(['did_number', 'contact_number']);
        });

        Schema::table('sms_messages', function (Blueprint $table) {
            $table->renameColumn('provider_message_id', 'telnyx_message_id');
            // Add idempotency key for webhook replay protection
            $table->string('telnyx_event_id')->nullable()->unique()->after('telnyx_message_id');
        });
    }

    public function down(): void
    {
        Schema::table('sms_messages', function (Blueprint $table) {
            $table->dropColumn('telnyx_event_id');
            $table->renameColumn('telnyx_message_id', 'provider_message_id');
        });

        Schema::table('sms_conversations', function (Blueprint $table) {
            $table->dropUnique(['did_number', 'contact_number']);
        });
        Schema::table('sms_conversations', function (Blueprint $table) {
            $table->unique(['our_number', 'their_number']);
            $table->renameColumn('did_number', 'our_number');
            $table->renameColumn('contact_number', 'their_number');
        });
    }
};
