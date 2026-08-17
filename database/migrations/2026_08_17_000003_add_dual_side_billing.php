<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Expression;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE ledger_entries ALTER COLUMN account_id DROP NOT NULL');
        Schema::table('users', function (Blueprint $table) {
            $table->string('customer_billing_mode')->default('SUBMISSION')->after('role');
            $table->string('provider_billing_mode')->default('SUBMISSION')->after('customer_billing_mode');
        });

        Schema::table('providers', function (Blueprint $table) {
            $table->string('billing_mode')->default('SUBMISSION')->after('failover_enabled');
        });

        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->foreignId('provider_id')->nullable()->after('account_id')->constrained()->nullOnDelete();
            $table->string('side')->default('CUSTOMER')->after('entry_type');
            $table->string('event_key')->nullable()->unique()->after('reference');
        });

        Schema::create('billing_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sms_message_id')->constrained()->cascadeOnDelete();
            $table->string('event_key')->unique();
            $table->string('side');
            $table->string('event_type');
            $table->decimal('amount', 20, 6)->default(0);
            $table->string('currency', 3)->default('BDT');
            $table->string('status')->default('POSTED');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_events');
        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->dropUnique(['event_key']);
            $table->dropForeign(['provider_id']);
            $table->dropColumn(['provider_id', 'side', 'event_key']);
        });
        Schema::table('providers', function (Blueprint $table) { $table->dropColumn('billing_mode'); });
        Schema::table('users', function (Blueprint $table) { $table->dropColumn(['customer_billing_mode', 'provider_billing_mode']); });
    }
};
