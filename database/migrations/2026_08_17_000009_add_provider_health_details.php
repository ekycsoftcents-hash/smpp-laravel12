<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('providers')) return;
        Schema::table('providers', function (Blueprint $table): void {
            if (!Schema::hasColumn('providers', 'health_error')) $table->text('health_error')->nullable()->after('last_health_at');
            if (!Schema::hasColumn('providers', 'health_latency_ms')) $table->decimal('health_latency_ms', 10, 2)->nullable()->after('health_error');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('providers')) return;
        Schema::table('providers', function (Blueprint $table): void {
            if (Schema::hasColumn('providers', 'health_latency_ms')) $table->dropColumn('health_latency_ms');
            if (Schema::hasColumn('providers', 'health_error')) $table->dropColumn('health_error');
        });
    }
};
