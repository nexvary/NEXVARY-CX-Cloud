<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── CRM — Customers ───────────────────────────────────────────────────

        Schema::create('contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type')->default('person'); // person, company
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('company_name')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone', 30)->nullable()->index();
            $table->string('phone_alt', 30)->nullable();
            $table->string('country', 3)->nullable();
            $table->text('notes')->nullable();
            $table->json('tags')->nullable();
            $table->foreignUuid('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('company_id')->nullable(); // self-reference for person->company
            $table->timestamps();
            $table->softDeletes();
        });

        // ── Support Tickets ────────────────────────────────────────────────────

        Schema::create('tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('subject');
            $table->text('description');
            $table->string('status')->default('open'); // open, in_progress, resolved, closed
            $table->string('priority')->default('normal'); // low, normal, high, urgent
            $table->string('department')->nullable();
            $table->foreignUuid('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('created_by')->constrained('users');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'priority', 'created_at']);
        });

        Schema::create('ticket_replies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained();
            $table->text('body');
            $table->boolean('internal')->default(false);
            $table->json('attachments')->nullable();
            $table->timestamps();
        });

        // ── SMS ───────────────────────────────────────────────────────────────

        Schema::create('sms_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('our_number', 30);
            $table->string('their_number', 30);
            $table->foreignUuid('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('open');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->unique(['our_number', 'their_number']);
            $table->index('last_message_at');
        });

        Schema::create('sms_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->constrained('sms_conversations')->cascadeOnDelete();
            $table->string('direction'); // inbound, outbound
            $table->text('body');
            $table->string('provider_message_id')->nullable()->unique();
            $table->string('status')->default('pending'); // pending, sent, delivered, failed, received
            $table->string('error_message')->nullable();
            $table->decimal('cost', 10, 6)->nullable();
            $table->foreignUuid('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->index(['conversation_id', 'created_at']);
        });

        // ── Email ─────────────────────────────────────────────────────────────

        Schema::create('email_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('message_id')->nullable()->unique();
            $table->string('direction'); // inbound, outbound
            $table->string('from_address');
            $table->string('from_name')->nullable();
            $table->json('to_addresses');
            $table->json('cc_addresses')->nullable();
            $table->string('subject');
            $table->longText('body_html')->nullable();
            $table->longText('body_text')->nullable();
            $table->json('attachments')->nullable();
            $table->string('status')->default('received'); // received, read, replied, archived
            $table->string('mailbox')->default('inbox');
            $table->foreignUuid('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('received_at');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['mailbox', 'status', 'received_at']);
        });

        // ── Billing ───────────────────────────────────────────────────────────

        Schema::create('billing_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->foreignUuid('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->default('postpaid'); // prepaid, postpaid
            $table->string('currency', 3)->default('USD');
            $table->decimal('balance', 15, 6)->default(0); // prepaid balance
            $table->decimal('credit_limit', 15, 2)->nullable(); // postpaid limit
            $table->decimal('daily_limit', 15, 2)->nullable(); // daily spend cap
            $table->uuid('rate_table_id')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('rate_tables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('rates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('rate_table_id')->constrained()->cascadeOnDelete();
            $table->string('prefix', 20); // E.164 prefix
            $table->string('description')->nullable();
            $table->decimal('rate_per_minute', 12, 6)->default(0);
            $table->decimal('connection_fee', 10, 6)->default(0);
            $table->integer('billing_increment')->default(60); // seconds
            $table->integer('minimum_duration')->default(0); // seconds
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->index(['rate_table_id', 'prefix']);
        });

        Schema::create('call_charges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('call_record_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('billing_account_id')->constrained()->cascadeOnDelete();
            $table->uuid('rate_table_id');
            $table->uuid('rate_id');
            $table->string('destination', 30);
            $table->string('prefix_matched', 20);
            $table->integer('duration_seconds');
            $table->integer('billable_seconds');
            $table->decimal('rate_per_minute', 12, 6);
            $table->decimal('connection_fee', 10, 6)->default(0);
            $table->decimal('amount', 15, 6);
            $table->string('currency', 3);
            $table->timestamp('rated_at');
            $table->timestamps();
            $table->index(['billing_account_id', 'rated_at']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('invoice_number')->unique();
            $table->foreignUuid('billing_account_id')->constrained();
            $table->string('status')->default('draft'); // draft, issued, paid, overdue, cancelled
            $table->date('period_from');
            $table->date('period_to');
            $table->decimal('subtotal', 15, 2);
            $table->decimal('tax', 15, 2)->default(0);
            $table->decimal('total', 15, 2);
            $table->string('currency', 3)->default('USD');
            $table->date('due_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('line_items')->nullable();
            $table->timestamps();
        });

        // ── Voice AI ──────────────────────────────────────────────────────────

        Schema::create('ai_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('call_uuid');
            $table->foreignUuid('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider')->default('openai');
            $table->string('model')->default('gpt-4o');
            $table->string('status')->default('active'); // active, transferred, ended
            $table->json('conversation')->nullable(); // conversation history
            $table->text('summary')->nullable();
            $table->string('intent')->nullable();
            $table->string('outcome')->nullable(); // resolved, transferred, abandoned
            $table->integer('duration_seconds')->nullable();
            $table->decimal('cost_usd', 10, 6)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_sessions');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('call_charges');
        Schema::dropIfExists('rates');
        Schema::dropIfExists('rate_tables');
        Schema::dropIfExists('billing_accounts');
        Schema::dropIfExists('email_messages');
        Schema::dropIfExists('sms_messages');
        Schema::dropIfExists('sms_conversations');
        Schema::dropIfExists('ticket_replies');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('contacts');
    }
};
