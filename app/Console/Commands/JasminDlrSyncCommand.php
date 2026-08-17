<?php

namespace App\Console\Commands;

use App\Services\Billing\BillingService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class JasminDlrSyncCommand extends Command
{
    protected $signature = 'jasmin:dlr-sync
        {--from= : Start timestamp, default is 24 hours ago}
        {--to= : End timestamp, default is now}
        {--limit=1000 : Maximum messages to inspect}
        {--commit : Apply returned DLR statuses; default is dry-run}';

    protected $description = 'Find messages missing DLR and optionally synchronize statuses from a configured Jasmin DLR lookup adapter.';

    public function handle(BillingService $billing): int
    {
        $from = Carbon::parse((string) ($this->option('from') ?: now()->subDay()->toDateTimeString()));
        $to = Carbon::parse((string) ($this->option('to') ?: now()->toDateTimeString()));
        $limit = max(1, (int) $this->option('limit'));
        $commit = (bool) $this->option('commit');
        $lookupUrl = (string) config('smpp.jasmin.dlr_lookup_url');

        $pending = DB::table('sms_messages')
            ->whereBetween('created_at', [$from, $to])
            ->whereNotNull('provider_submitted_at')
            ->whereNull('dlr_at')
            ->whereIn('final_status', ['SUBMITTED', 'QUEUED'])
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $this->info(sprintf('Found %d messages without DLR between %s and %s.', $pending->count(), $from->toDateTimeString(), $to->toDateTimeString()));

        if ($pending->isEmpty()) return self::SUCCESS;
        if ($lookupUrl === '') {
            $this->warn('JASMIN_DLR_LOOKUP_URL is not configured. Jasmin normally delivers DLR through the configured callback; it does not expose a standard HTTP fetch endpoint.');
            $this->line('Configure an internal DLR lookup adapter or replay source before using --commit.');
            return self::INVALID;
        }

        $synced = 0;
        $unavailable = 0;
        foreach ($pending as $message) {
            $metadata = json_decode((string) ($message->metadata ?: '{}'), true) ?: [];
            $jasminId = $metadata['jasmin_message_id'] ?? null;
            if (!$jasminId) {
                $this->warn("SMS #{$message->id} has no jasmin_message_id.");
                $unavailable++;
                continue;
            }

            try {
                $response = Http::connectTimeout((int) config('smpp.jasmin.connect_timeout', 5))
                    ->timeout((int) config('smpp.jasmin.timeout', 20))
                    ->acceptJson()
                    ->get($lookupUrl, [
                        'message_id' => $message->message_id,
                        'jasmin_message_id' => $jasminId,
                    ]);

                if (!$response->successful()) {
                    $this->warn("SMS #{$message->id}: lookup adapter returned HTTP {$response->status()}.");
                    $unavailable++;
                    continue;
                }

                $payload = $response->json() ?: [];
                $rawStatus = strtoupper((string) ($payload['status'] ?? $payload['message_status'] ?? ''));
                $mapped = match (true) {
                    in_array($rawStatus, ['DELIVRD', 'DELIVERED'], true) => 'DELIVERED',
                    in_array($rawStatus, ['UNDELIV', 'FAILED', 'REJECTD', 'REJECTED'], true) => 'FAILED',
                    in_array($rawStatus, ['EXPIRED', 'EXPIRD'], true) => 'EXPIRED',
                    default => null,
                };

                if (!$mapped) {
                    $this->line("SMS #{$message->id}: lookup returned no final DLR status.");
                    $unavailable++;
                    continue;
                }

                $this->line("SMS #{$message->id}: {$rawStatus} -> {$mapped}" . ($commit ? ' [commit]' : ' [dry-run]'));
                if ($commit) {
                    DB::transaction(function () use ($billing, $message, $mapped, $rawStatus): void {
                        $locked = DB::table('sms_messages')->where('id', $message->id)->lockForUpdate()->first();
                        if (!$locked || $locked->dlr_at) return;
                        DB::table('sms_messages')->where('id', $message->id)->update([
                            'final_status' => $mapped,
                            'provider_status' => $rawStatus,
                            'customer_status' => $mapped,
                            'dlr_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $billing->onDlr((int) $message->id, $mapped);
                    });
                }
                $synced++;
            } catch (Throwable $exception) {
                $this->error("SMS #{$message->id}: {$exception->getMessage()}");
                $unavailable++;
            }
        }

        $this->table(['Metric', 'Count'], [
            ['Found without DLR', $pending->count()],
            ['Status returned', $synced],
            ['Unavailable/unknown', $unavailable],
            ['Mode', $commit ? 'COMMIT' : 'DRY-RUN'],
        ]);

        if (!$commit) $this->comment('Dry-run only. Add --commit after reviewing the lookup results.');
        return $unavailable > 0 ? self::FAILURE : self::SUCCESS;
    }
}
