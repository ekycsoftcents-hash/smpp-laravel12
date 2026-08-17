<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $userColumns = [
            'customer_billing_mode' => fn (Blueprint $table) => $table->string('customer_billing_mode')->default('SUBMISSION'),
            'provider_billing_mode' => fn (Blueprint $table) => $table->string('provider_billing_mode')->default('SUBMISSION'),
            'billing_policy' => fn (Blueprint $table) => $table->string('billing_policy')->default('PREPAID'),
            'credit_limit' => fn (Blueprint $table) => $table->decimal('credit_limit', 24, 6)->default(0),
            'credit_used' => fn (Blueprint $table) => $table->decimal('credit_used', 24, 6)->default(0),
            'credit_due_at' => fn (Blueprint $table) => $table->timestamp('credit_due_at')->nullable(),
        ];

        foreach ($userColumns as $column => $definition) {
            if (!Schema::hasColumn('users', $column)) {
                Schema::table('users', $definition);
            }
        }

        $providerColumns = [
            'billing_mode' => fn (Blueprint $table) => $table->string('billing_mode')->default('SUBMISSION'),
            'settlement_policy' => fn (Blueprint $table) => $table->string('settlement_policy')->default('DUE'),
            'credit_limit' => fn (Blueprint $table) => $table->decimal('credit_limit', 24, 6)->default(0),
            'credit_used' => fn (Blueprint $table) => $table->decimal('credit_used', 24, 6)->default(0),
            'payment_terms_days' => fn (Blueprint $table) => $table->unsignedInteger('payment_terms_days')->default(30),
        ];

        foreach ($providerColumns as $column => $definition) {
            if (!Schema::hasColumn('providers', $column)) {
                Schema::table('providers', $definition);
            }
        }
    }

    public function down(): void
    {
        // This repair migration is intentionally non-destructive. The original
        // feature migration remains responsible for removing its own columns.
    }
};
