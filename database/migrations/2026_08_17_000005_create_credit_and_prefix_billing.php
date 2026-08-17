<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('billing_policy')->default('PREPAID')->after('provider_billing_mode');
            $table->decimal('credit_limit', 24, 6)->default(0)->change();
            $table->decimal('credit_used', 24, 6)->default(0)->after('credit_limit');
            $table->timestamp('credit_due_at')->nullable()->after('credit_used');
        });
        Schema::table('providers', function (Blueprint $table) {
            $table->string('settlement_policy')->default('DUE')->after('billing_mode');
            $table->decimal('credit_limit', 24, 6)->default(0)->after('buy_rate');
            $table->decimal('credit_used', 24, 6)->default(0)->after('credit_limit');
            $table->unsignedInteger('payment_terms_days')->default(30)->after('credit_used');
        });
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('account_type');
            $table->unsignedBigInteger('account_id');
            $table->string('currency', 3)->default('BDT');
            $table->string('transaction_type');
            $table->decimal('amount', 24, 6);
            $table->decimal('balance_after', 24, 6)->default(0);
            $table->string('reference')->nullable()->index();
            $table->text('description')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamps();
            $table->index(['account_type', 'account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
        Schema::table('providers', function (Blueprint $table) {
            $table->dropColumn(['settlement_policy', 'credit_limit', 'credit_used', 'payment_terms_days']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['billing_policy', 'credit_used', 'credit_due_at']);
        });
    }
};
