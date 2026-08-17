<?php

namespace App\Console\Commands;

use App\Services\Billing\BillingService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class BillingReconcileCommand extends Command
{
    protected $signature = 'billing:reconcile
        {--from= : Start timestamp, for example 2026-08-17 00:00:00}
        {--to= : End timestamp, for example 2026-08-17 23:59:59}
        {--limit=1000 : Maximum number of messages to inspect}
        {--commit : Apply missing billing events; without this option the command is dry-run only}';

    protected $description = 'Find and optionally post missing idempotent customer/provider billing events after downtime.';

    public function handle(BillingService $billing): int
    {
        $from = $this->parseTime((string) ($this->option('from') ?: now()->subDay()->toDateTimeString()));
        $to = $this->parseTime((string) ($this->option('to') ?: now()->toDateTimeString()));
        $limit = max(1, (int) $this->option('limit'));
        $commit = (bool) $this->option('commit');

        if ($from->greaterThan($to)) {
            $this->error('--from must be earlier than or equal to --to.');
            return self::FAILURE;
        }

        $this->line(sprintf(
            '%s billing reconciliation from %s to %s (limit %d)',
            $commit ? 'COMMIT' : 'DRY-RUN',
            $from->toDateTimeString(),
            $to->toDateTimeString(),
            $limit
        ));

        $messages = DB::table('sms_messages')
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('final_status', ['SUBMITTED', 'QUEUED', 'DELIVERED', 'FAILED', 'REJECTED', 'EXPIRED'])
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $summary = ['inspected' => 0, 'missing' => 0, 'reconciled' => 0, 'failed' => 0];

        foreach ($messages as $sms) {
            $summary['inspected']++;
            $actions = $this->missingActions($sms);
            if ($actions === []) continue;

            $summary['missing'] += count($actions);
            $this->line(sprintf(
                'SMS #%d %s status=%s: %s',
                $sms->id,
                $sms->message_id,
                $sms->final_status,
                implode(', ', $actions)
            ));

            if (!$commit) continue;

            try {
                DB::transaction(function () use ($billing, $sms, $actions): void {
                    if (in_array('SUBMISSION', $actions, true)) {
                        $billing->onSubmission((int) $sms->id);
                    }
                    if (in_array('DLR:DELIVERED', $actions, true)) {
                        $billing->onDlr((int) $sms->id, 'DELIVERED');
                    }
                    if (in_array('DLR:FAILED', $actions, true)) {
                        $billing->onDlr((int) $sms->id, 'FAILED');
                    }
                    if (in_array('DLR:REJECTED', $actions, true)) {
                        $billing->onDlr((int) $sms->id, 'REJECTED');
                    }
                    if (in_array('DLR:EXPIRED', $actions, true)) {
                        $billing->onDlr((int) $sms->id, 'EXPIRED');
                    }
                });
                $summary['reconciled']++;
            } catch (Throwable $exception) {
                $summary['failed']++;
                $this->error(sprintf('SMS #%d failed: %s', $sms->id, $exception->getMessage()));
            }
        }

        $this->newLine();
        $this->table(['Metric', 'Count'], [
            ['Messages inspected', $summary['inspected']],
            ['Missing events found', $summary['missing']],
            ['Messages reconciled', $summary['reconciled']],
            ['Messages failed', $summary['failed']],
        ]);

        if (!$commit) {
            $this->comment('Dry-run only. Re-run the same command with --commit to apply the listed changes.');
        }

        return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function missingActions(object $sms): array
    {
        $status = strtoupper((string) $sms->final_status);
        $submitted = in_array($status, ['SUBMITTED', 'QUEUED', 'DELIVERED', 'FAILED', 'REJECTED', 'EXPIRED'], true)
            || !empty($sms->provider_submitted_at);
        if (!$submitted) return [];

        $customerMode = $sms->customer_id
            ? strtoupper((string) (DB::table('users')->where('id', $sms->customer_id)->value('customer_billing_mode') ?: 'SUBMISSION'))
            : null;
        $providerMode = $sms->provider_id
            ? strtoupper((string) (DB::table('providers')->where('id', $sms->provider_id)->value('billing_mode') ?: 'SUBMISSION'))
            : null;

        $events = DB::table('billing_events')
            ->where('sms_message_id', $sms->id)
            ->pluck('event_key')
            ->all();
        $events = array_fill_keys($events, true);
        $actions = [];

        if ($customerMode === 'SUBMISSION' && $sms->customer_id && !$this->has($events, 'customer:sms_submission_charge:' . $sms->id)) {
            $actions[] = 'SUBMISSION';
        }
        if ($providerMode === 'SUBMISSION' && $sms->provider_id && !$this->has($events, 'provider:provider_submission_cost:' . $sms->id)) {
            $actions[] = 'SUBMISSION';
        }

        $dlrAction = match ($status) {
            'DELIVERED' => 'DLR:DELIVERED',
            'FAILED' => 'DLR:FAILED',
            'REJECTED' => 'DLR:REJECTED',
            'EXPIRED' => 'DLR:EXPIRED',
            default => null,
        };

        if ($dlrAction === null) return array_values(array_unique($actions));

        if ($customerMode === 'DLR' && $status === 'DELIVERED' && $sms->customer_id && !$this->has($events, 'customer:sms_dlr_charge:' . $sms->id)) {
            $actions[] = $dlrAction;
        }
        if ($customerMode === 'SUBMISSION' && in_array($status, ['FAILED', 'REJECTED', 'EXPIRED'], true) && $sms->customer_id && !$this->has($events, 'customer:sms_refund:' . $sms->id)) {
            $actions[] = $dlrAction;
        }
        if ($providerMode === 'DLR' && $status === 'DELIVERED' && $sms->provider_id && !$this->has($events, 'provider:provider_dlr_cost:' . $sms->id)) {
            $actions[] = $dlrAction;
        }
        if ($providerMode === 'SUBMISSION' && in_array($status, ['FAILED', 'REJECTED', 'EXPIRED'], true) && $sms->provider_id && !$this->has($events, 'provider:provider_refund:' . $sms->id)) {
            $actions[] = $dlrAction;
        }

        return array_values(array_unique($actions));
    }

    private function has(array $events, string $key): bool
    {
        return isset($events[strtolower($key)]);
    }

    private function parseTime(string $value): Carbon
    {
        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            throw new \InvalidArgumentException("Invalid timestamp: {$value}");
        }
    }
}
