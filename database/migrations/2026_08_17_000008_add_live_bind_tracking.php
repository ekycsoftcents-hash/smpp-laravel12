<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('customer_smpp_accounts')) return;
        Schema::table('customer_smpp_accounts', function (Blueprint $table): void {
            if (!Schema::hasColumn('customer_smpp_accounts', 'current_binds')) $table->unsignedSmallInteger('current_binds')->default(0)->after('max_bind');
            if (!Schema::hasColumn('customer_smpp_accounts', 'last_bind_at')) $table->timestamp('last_bind_at')->nullable()->after('current_binds');
            if (!Schema::hasColumn('customer_smpp_accounts', 'last_unbind_at')) $table->timestamp('last_unbind_at')->nullable()->after('last_bind_at');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('customer_smpp_accounts')) return;
        Schema::table('customer_smpp_accounts', function (Blueprint $table): void {
            foreach (['current_binds', 'last_bind_at', 'last_unbind_at'] as $column) {
                if (Schema::hasColumn('customer_smpp_accounts', $column)) $table->dropColumn($column);
            }
        });
    }
};
