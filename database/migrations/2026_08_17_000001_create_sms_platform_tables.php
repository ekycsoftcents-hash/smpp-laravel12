<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('account_type')->default('admin')->index();
            $table->string('role')->default('operator')->index();
            $table->foreignId('parent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('currency', 3)->default('BDT');
            $table->decimal('balance', 20, 6)->default(0);
            $table->decimal('credit_limit', 20, 6)->default(0);
            $table->decimal('commission_rate', 10, 4)->default(0);
            $table->json('permissions')->nullable();
        });

        Schema::create('providers', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('host'); $table->unsignedInteger('port')->default(2775);
            $table->string('username'); $table->text('password'); $table->string('system_type')->nullable(); $table->string('bind_type')->default('transceiver');
            $table->string('status')->default('DISCONNECTED')->index(); $table->boolean('dlr_support')->default(true); $table->boolean('failover_enabled')->default(true);
            $table->decimal('tps', 12, 3)->default(1); $table->unsignedInteger('connection_limit')->default(1); $table->decimal('buy_rate', 20, 6)->default(0);
            $table->string('country')->nullable(); $table->string('operator')->nullable(); $table->string('prefix')->nullable(); $table->unsignedInteger('priority')->default(100);
            $table->timestamp('last_health_at')->nullable(); $table->timestamps();
        });

        Schema::create('routing_rules', function (Blueprint $table) {
            $table->id(); $table->foreignId('provider_id')->constrained()->cascadeOnDelete(); $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type'); $table->string('country')->nullable(); $table->string('operator')->nullable(); $table->string('prefix')->nullable(); $table->string('sender_id')->nullable();
            $table->string('strategy')->default('priority'); $table->decimal('percentage', 8, 4)->default(100); $table->unsignedInteger('priority')->default(100); $table->boolean('enabled')->default(true); $table->timestamps();
        });

        Schema::create('rates', function (Blueprint $table) {
            $table->id(); $table->foreignId('provider_id')->nullable()->constrained()->nullOnDelete(); $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type'); $table->string('country')->nullable(); $table->string('operator')->nullable(); $table->string('prefix')->nullable(); $table->string('sender_id')->nullable();
            $table->decimal('buy_rate', 20, 6)->default(0); $table->decimal('sell_rate', 20, 6)->default(0); $table->string('currency', 3)->default('BDT'); $table->timestamp('effective_from'); $table->timestamp('effective_until')->nullable(); $table->timestamps();
        });

        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id(); $table->uuid('message_id')->unique(); $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete(); $table->foreignId('reseller_id')->nullable()->constrained('users')->nullOnDelete(); $table->foreignId('provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source'); $table->string('destination'); $table->text('message'); $table->unsignedSmallInteger('segments')->default(1); $table->string('final_status')->default('UNKNOWN')->index(); $table->string('provider_status')->nullable(); $table->string('customer_status')->nullable();
            $table->decimal('buy_rate', 20, 6)->default(0); $table->decimal('sell_rate', 20, 6)->default(0); $table->decimal('customer_charge', 20, 6)->default(0); $table->decimal('provider_cost', 20, 6)->default(0); $table->decimal('profit', 20, 6)->default(0); $table->string('currency', 3)->default('BDT');
            $table->timestamp('submitted_at')->nullable(); $table->timestamp('provider_submitted_at')->nullable(); $table->timestamp('dlr_at')->nullable(); $table->string('idempotency_key')->nullable()->unique(); $table->json('metadata')->nullable(); $table->timestamps();
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id(); $table->foreignId('account_id')->constrained('users')->cascadeOnDelete(); $table->foreignId('sms_message_id')->nullable()->constrained()->nullOnDelete(); $table->string('entry_type'); $table->decimal('debit', 20, 6)->default(0); $table->decimal('credit', 20, 6)->default(0); $table->decimal('balance_after', 20, 6)->default(0); $table->string('currency', 3)->default('BDT'); $table->string('reference')->nullable(); $table->text('description')->nullable(); $table->timestamps();
        });

        Schema::create('webhooks', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->string('event'); $table->text('url'); $table->text('secret')->nullable(); $table->boolean('enabled')->default(true); $table->unsignedSmallInteger('retry_limit')->default(5); $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); $table->string('action'); $table->string('auditable_type')->nullable(); $table->unsignedBigInteger('auditable_id')->nullable(); $table->json('before')->nullable(); $table->json('after')->nullable(); $table->string('ip_address', 45)->nullable(); $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs'); Schema::dropIfExists('webhooks'); Schema::dropIfExists('ledger_entries'); Schema::dropIfExists('sms_messages'); Schema::dropIfExists('rates'); Schema::dropIfExists('routing_rules'); Schema::dropIfExists('providers');
        Schema::table('users', function (Blueprint $table) { $table->dropForeign(['parent_id']); $table->dropColumn(['account_type','role','parent_id','currency','balance','credit_limit','commission_rate','permissions']); });
    }
};

