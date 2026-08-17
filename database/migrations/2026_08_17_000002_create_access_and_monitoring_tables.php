<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('api_credentials', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->string('name'); $table->string('key')->unique(); $table->text('secret'); $table->json('ip_whitelist')->nullable(); $table->unsignedInteger('rate_limit_per_minute')->default(60); $table->boolean('enabled')->default(true); $table->timestamp('last_used_at')->nullable(); $table->timestamps();
        });
        Schema::create('customer_smpp_accounts', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete(); $table->string('system_id')->unique(); $table->text('password'); $table->json('ip_whitelist')->nullable(); $table->unsignedSmallInteger('max_bind')->default(1); $table->decimal('tps', 12, 3)->default(1); $table->unsignedInteger('daily_limit')->nullable(); $table->unsignedInteger('monthly_limit')->nullable(); $table->json('sender_rules')->nullable(); $table->json('country_rules')->nullable(); $table->json('prefix_rules')->nullable(); $table->boolean('enabled')->default(true); $table->timestamps();
        });
        Schema::create('refund_policies', function (Blueprint $table) {
            $table->id(); $table->string('side'); $table->string('status'); $table->decimal('refund_percent', 8, 4)->default(0); $table->boolean('enabled')->default(true); $table->timestamps();
        });
        Schema::create('alerts', function (Blueprint $table) {
            $table->id(); $table->string('type'); $table->string('severity')->default('warning'); $table->string('subject'); $table->text('message'); $table->json('context')->nullable(); $table->timestamp('resolved_at')->nullable(); $table->timestamps();
        });
        Schema::create('system_metrics', function (Blueprint $table) {
            $table->id(); $table->string('metric'); $table->decimal('value', 20, 6)->default(0); $table->string('unit')->nullable(); $table->timestamp('recorded_at')->index();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('system_metrics'); Schema::dropIfExists('alerts'); Schema::dropIfExists('refund_policies'); Schema::dropIfExists('customer_smpp_accounts'); Schema::dropIfExists('api_credentials');
    }
};
