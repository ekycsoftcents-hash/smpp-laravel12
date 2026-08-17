<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('billing_events') || !Schema::hasColumn('billing_events', 'event_key')) {
            return;
        }

        $duplicates = DB::table('billing_events')
            ->select('event_key')
            ->whereNotNull('event_key')
            ->groupBy('event_key')
            ->havingRaw('COUNT(*) > 1')
            ->limit(10)
            ->pluck('event_key')
            ->all();

        if ($duplicates !== []) {
            throw new \RuntimeException('Cannot add billing_events.event_key unique constraint; duplicate keys: ' . implode(', ', $duplicates));
        }

        $hasUnique = DB::table('pg_indexes')
            ->where('tablename', 'billing_events')
            ->where('indexdef', 'ilike', '%unique%')
            ->where('indexdef', 'ilike', '%event_key%')
            ->exists();

        if (!$hasUnique) {
            Schema::table('billing_events', function (Blueprint $table): void {
                $table->unique('event_key', 'billing_events_event_key_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('billing_events')) {
            Schema::table('billing_events', function (Blueprint $table): void {
                $table->dropUnique('billing_events_event_key_unique');
            });
        }
    }
};
