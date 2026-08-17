<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\Jasmin\JasminSmppProvisioningService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function users()
    {
        $users = DB::table('users')->leftJoin('customer_smpp_accounts as smpp', 'smpp.user_id', '=', 'users.id')->select('users.*', 'smpp.system_id', 'smpp.max_bind', 'smpp.enabled as smpp_enabled')->orderByDesc('users.id')->paginate(25);
        return view('admin.users', compact('users'));
    }

    public function storeUser(Request $request, JasminSmppProvisioningService $jasmin)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'account_type' => ['required', 'in:admin,customer,reseller,operator'],
            'role' => ['required', 'string', 'max:80'],
            'customer_billing_mode' => ['required', 'in:SUBMISSION,DLR'],
            'provider_billing_mode' => ['required', 'in:SUBMISSION,DLR'],
            'billing_policy' => ['required', 'in:DUE,PREPAID'],
            'credit_limit' => ['required', 'numeric', 'min:0'],
            'system_id' => ['required', 'alpha_dash', 'min:3', 'max:16', 'unique:customer_smpp_accounts,system_id'],
            'smpp_password' => ['required', 'string', 'min:8', 'max:64'],
            'max_bind' => ['required', 'integer', 'between:1,20'],
            'tps' => ['required', 'numeric', 'gt:0', 'max:1000'],
        ]);
        $plainPassword = $data['password'];
        $smppPassword = $data['smpp_password'];
        $systemId = $data['system_id']; $maxBind = (int) $data['max_bind']; $tps = (float) $data['tps'];
        unset($data['smpp_password'], $data['system_id'], $data['max_bind'], $data['tps']);
        $userId = DB::transaction(function () use ($data, $plainPassword, $smppPassword, $systemId, $maxBind, $tps, $jasmin) {
            $data['password'] = Hash::make($plainPassword);
            $data['email_verified_at'] = now();
            $userId = DB::table('users')->insertGetId(array_merge($data, ['balance' => 1.000000, 'created_at' => now(), 'updated_at' => now()]));
            $jasmin->provision('u' . $userId, $systemId, $smppPassword, $maxBind, $tps);
            DB::table('customer_smpp_accounts')->insert([
                'user_id' => $userId, 'system_id' => $systemId, 'password' => encrypt($smppPassword),
                'max_bind' => $maxBind, 'tps' => $tps, 'enabled' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
            return $userId;
        });
        return back()->with('success', "User created and Jasmin SMPP account provisioned: {$systemId}");
    }

    public function providers()
    {
        return view('admin.providers', ['providers' => DB::table('providers')->latest()->paginate(25)]);
    }

    public function storeProvider(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'host' => ['required', 'string', 'max:190'],
            'port' => ['required', 'integer', 'between:1,65535'], 'username' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string', 'max:255'], 'buy_rate' => ['required', 'numeric', 'min:0'],
            'country' => ['nullable', 'string', 'max:80'], 'priority' => ['required', 'integer', 'min:1'],
            'billing_mode' => ['required', 'in:SUBMISSION,DLR'],
            'settlement_policy' => ['required', 'in:DUE,PREPAID'],
            'credit_limit' => ['required', 'numeric', 'min:0'],
            'payment_terms_days' => ['required', 'integer', 'min:0'],
        ]);
        $data['password'] = encrypt($data['password']);
        $data['created_at'] = now(); $data['updated_at'] = now();
        DB::table('providers')->insert($data);
        return back()->with('success', 'Provider created successfully.');
    }

    public function rates()
    {
        $rates = DB::table('rates')->leftJoin('providers', 'providers.id', '=', 'rates.provider_id')->select('rates.*', 'providers.name as provider_name')->latest('rates.id')->paginate(25);
        return view('admin.rates', ['rates' => $rates, 'providers' => DB::table('providers')->orderBy('name')->get(), 'customers' => DB::table('users')->whereIn('account_type', ['customer', 'reseller'])->orderBy('name')->get()]);
    }

    public function storeRate(Request $request)
    {
        $data = $request->validate([
            'provider_id' => ['nullable', 'exists:providers,id'], 'customer_id' => ['nullable', 'exists:users,id'],
            'type' => ['required', 'string', 'max:50'], 'country' => ['nullable', 'string', 'max:80'],
            'prefix' => ['nullable', 'string', 'max:40'], 'buy_rate' => ['required', 'numeric', 'min:0'],
            'sell_rate' => ['required', 'numeric', 'min:0'], 'currency' => ['required', 'string', 'size:3'],
            'buy_currency' => ['required', 'string', 'size:3'],
            'sell_currency' => ['required', 'string', 'size:3'],
        ]);
        DB::table('rates')->insert(array_merge($data, ['effective_from' => now(), 'created_at' => now(), 'updated_at' => now()]));
        return back()->with('success', 'Rate created successfully.');
    }

    public function importRates(Request $request)
    {
        $request->validate(['tariff_file' => ['required', 'file', 'max:10240', 'mimes:csv,txt,xlsx']]);
        $file = $request->file('tariff_file');
        $rows = strtolower($file->getClientOriginalExtension()) === 'xlsx'
            ? $this->readXlsxRows($file->getRealPath())
            : $this->readCsvRows($file->getRealPath());
        if (!$rows) return back()->withErrors(['tariff_file' => 'The uploaded file has no data rows.']);

        $required = ['type', 'prefix', 'buy_rate', 'sell_rate', 'currency', 'buy_currency', 'sell_currency'];
        $imported = 0; $failed = [];
        DB::transaction(function () use ($rows, $required, &$imported, &$failed): void {
            foreach ($rows as $line => $row) {
                $row = array_change_key_case($row, CASE_LOWER);
                $row = array_combine(array_map(fn ($key) => preg_replace('/[^a-z0-9_]+/', '_', trim((string) $key)), array_keys($row)), array_values($row));
                $missing = array_values(array_filter($required, fn ($key) => !array_key_exists($key, $row)));
                if ($missing) { $failed[$line] = 'Missing columns: ' . implode(', ', $missing); continue; }
                $validator = validator($row, [
                    'provider_id' => ['nullable', 'integer', 'exists:providers,id'],
                    'customer_id' => ['nullable', 'integer', 'exists:users,id'],
                    'type' => ['required', 'string', 'max:50'],
                    'country' => ['nullable', 'string', 'max:80'],
                    'prefix' => ['nullable', 'string', 'max:40'],
                    'buy_rate' => ['required', 'numeric', 'min:0'],
                    'sell_rate' => ['required', 'numeric', 'min:0'],
                    'currency' => ['required', 'string', 'size:3'],
                    'buy_currency' => ['required', 'string', 'size:3'],
                    'sell_currency' => ['required', 'string', 'size:3'],
                ]);
                if ($validator->fails()) { $failed[$line] = implode(' ', $validator->errors()->all()); continue; }
                $data = $validator->validated();
                $data['provider_id'] = $this->nullableInt($data['provider_id'] ?? null);
                $data['customer_id'] = $this->nullableInt($data['customer_id'] ?? null);
                $data['type'] = strtoupper($data['type']);
                $data['country'] = $data['country'] ?? null;
                $data['prefix'] = $data['prefix'] ?? null;
                $data['currency'] = strtoupper($data['currency']);
                $data['buy_currency'] = strtoupper($data['buy_currency']);
                $data['sell_currency'] = strtoupper($data['sell_currency']);
                $data['effective_from'] = now();
                $data['updated_at'] = now();
                $match = DB::table('rates')->where('type', $data['type']);
                foreach (['provider_id', 'customer_id', 'country', 'prefix'] as $key) {
                    $data[$key] === null ? $match->whereNull($key) : $match->where($key, $data[$key]);
                }
                $existing = $match->first(['id']);
                if ($existing) DB::table('rates')->where('id', $existing->id)->update($data);
                else DB::table('rates')->insert($data + ['created_at' => now()]);
                $imported++;
            }
        });
        return back()->with('rate_import_report', ['imported' => $imported, 'failed' => $failed, 'total' => count($rows)]);
    }

    public function downloadRateTemplate()
    {
        $headers = ['provider_id', 'customer_id', 'type', 'country', 'prefix', 'buy_rate', 'sell_rate', 'currency', 'buy_currency', 'sell_currency'];
        $example = ['', '', 'SMS', 'BD', '88017', '0.450000', '0.800000', 'BDT', 'BDT', 'BDT'];
        return response()->streamDownload(function () use ($headers, $example): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers); fputcsv($handle, $example); fclose($handle);
        }, 'tariff-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function readCsvRows(string $path): array
    {
        $handle = fopen($path, 'rb'); if (!$handle) return [];
        $headers = array_map(fn ($header) => preg_replace('/^\\xEF\\xBB\\xBF/', '', trim((string) $header)), fgetcsv($handle) ?: []); $rows = []; $line = 1;
        while (($values = fgetcsv($handle)) !== false) {
            $line++; if (count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) continue;
            $values = array_slice(array_pad($values, count($headers), null), 0, count($headers));
            $rows[$line] = array_combine($headers, $values) ?: [];
        }
        fclose($handle); return $rows;
    }

    private function readXlsxRows(string $path): array
    {
        $zip = new \ZipArchive(); if ($zip->open($path) !== true) return [];
        $shared = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $doc = simplexml_load_string($xml);
            foreach ($doc->si as $item) $shared[] = (string) ($item->t ?? implode('', array_map('strval', $item->r->t ?? [])));
        }
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml'); $zip->close();
        if ($sheet === false) return [];
        $doc = simplexml_load_string($sheet); $matrix = [];
        foreach ($doc->sheetData->row as $xmlRow) {
            $values = [];
            foreach ($xmlRow->c as $cell) {
                $ref = (string) $cell['r']; preg_match('/^([A-Z]+)/', $ref, $match); $column = 0;
                foreach (str_split($match[1] ?? 'A') as $char) $column = $column * 26 + (ord($char) - 64);
                $value = (string) ($cell->v ?? ''); if ((string) $cell['t'] === 's') $value = $shared[(int) $value] ?? '';
                $values[$column - 1] = $value;
            }
            $matrix[] = $values;
        }
        $headers = array_map(fn ($value) => (string) $value, array_shift($matrix) ?: []); $rows = []; $line = 1;
        foreach ($matrix as $values) { $line++; $rows[$line] = array_combine($headers, array_pad($values, count($headers), null)) ?: []; }
        return $rows;
    }

    public function routing()
    {
        $rules = DB::table('routing_rules')->leftJoin('providers', 'providers.id', '=', 'routing_rules.provider_id')->select('routing_rules.*', 'providers.name as provider_name')->latest('routing_rules.id')->paginate(25);
        return view('admin.routing', ['rules' => $rules, 'providers' => DB::table('providers')->orderBy('name')->get(), 'customers' => DB::table('users')->whereIn('account_type', ['customer', 'reseller'])->orderBy('name')->get()]);
    }

    public function storeRouting(Request $request)
    {
        $data = $request->validate([
            'provider_id' => ['required', 'exists:providers,id'], 'customer_id' => ['nullable', 'exists:users,id'],
            'type' => ['required', 'string', 'max:50'], 'country' => ['nullable', 'string', 'max:80'],
            'prefix' => ['nullable', 'string', 'max:40'], 'strategy' => ['required', 'string', 'max:50'],
            'priority' => ['required', 'integer', 'min:1'],
        ]);
        $data['enabled'] = true; $data['created_at'] = now(); $data['updated_at'] = now();
        DB::table('routing_rules')->insert($data);
        return back()->with('success', 'Routing rule created successfully.');
    }

    public function updateUser(Request $request, int $userId, JasminSmppProvisioningService $jasmin)
    {
        $account = DB::table('customer_smpp_accounts')->where('user_id', $userId)->first();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($userId)],
            'account_type' => ['required', 'in:admin,customer,reseller,operator'],
            'role' => ['required', 'string', 'max:80'],
            'customer_billing_mode' => ['required', 'in:SUBMISSION,DLR'],
            'provider_billing_mode' => ['required', 'in:SUBMISSION,DLR'],
            'billing_policy' => ['required', 'in:DUE,PREPAID'],
            'credit_limit' => ['required', 'numeric', 'min:0'],
            'balance' => ['required', 'numeric'],
            'currency' => ['required', 'string', 'size:3'],
            'password' => ['nullable', 'string', 'min:8'],
            'system_id' => ['required', 'alpha_dash', 'min:3', 'max:16', Rule::unique('customer_smpp_accounts', 'system_id')->ignore($userId, 'user_id')],
            'smpp_password' => ['nullable', 'string', 'min:8', 'max:64'],
            'max_bind' => ['required', 'integer', 'between:1,20'],
            'tps' => ['required', 'numeric', 'gt:0', 'max:1000'],
            'smpp_enabled' => ['required', 'boolean'],
        ]);
        $plainSmppPassword = $data['smpp_password'] ?? null;
        $systemId = $data['system_id'];
        $maxBind = (int) $data['max_bind'];
        $tps = (float) $data['tps'];
        $smppEnabled = (bool) $data['smpp_enabled'];
        unset($data['system_id'], $data['smpp_password'], $data['max_bind'], $data['tps'], $data['smpp_enabled']);
        if (!empty($data['password'])) $data['password'] = Hash::make($data['password']);
        else unset($data['password']);
        $balance = $data['balance'];
        unset($data['balance']);

        DB::transaction(function () use ($data, $balance, $userId, $account, $jasmin, $systemId, $plainSmppPassword, $maxBind, $tps, $smppEnabled): void {
            DB::table('users')->where('id', $userId)->update(array_merge($data, ['balance' => $balance, 'updated_at' => now()]));
            if ($account) {
                $jasmin->update('u' . $userId, $systemId, $plainSmppPassword, $maxBind, $tps);
                if ($smppEnabled) $jasmin->enable('u' . $userId); else $jasmin->disable('u' . $userId);
                $local = ['system_id' => $systemId, 'max_bind' => $maxBind, 'tps' => $tps, 'enabled' => $smppEnabled, 'updated_at' => now()];
                if ($plainSmppPassword !== null && $plainSmppPassword !== '') $local['password'] = encrypt($plainSmppPassword);
                DB::table('customer_smpp_accounts')->where('user_id', $userId)->update($local);
            }
        });
        return back()->with('success', 'User and Jasmin SMPP account updated successfully.');
    }

    public function destroyUser(int $userId, JasminSmppProvisioningService $jasmin)
    {
        $account = DB::table('customer_smpp_accounts')->where('user_id', $userId)->first();
        if ($account) {
            $jasmin->delete('u' . $userId);
            DB::table('customer_smpp_accounts')->where('user_id', $userId)->delete();
        }
        DB::table('users')->where('id', $userId)->delete();
        return back()->with('success', 'User and Jasmin SMPP account deleted successfully.');
    }

    public function updateProvider(Request $request, int $providerId)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'host' => ['required', 'string', 'max:190'],
            'port' => ['required', 'integer', 'between:1,65535'], 'username' => ['required', 'string', 'max:120'],
            'password' => ['nullable', 'string', 'max:255'], 'buy_rate' => ['required', 'numeric', 'min:0'],
            'country' => ['nullable', 'string', 'max:80'], 'priority' => ['required', 'integer', 'min:1'],
            'billing_mode' => ['required', 'in:SUBMISSION,DLR'], 'settlement_policy' => ['required', 'in:DUE,PREPAID'],
            'credit_limit' => ['required', 'numeric', 'min:0'], 'payment_terms_days' => ['required', 'integer', 'min:0'],
        ]);
        if (!empty($data['password'])) $data['password'] = encrypt($data['password']); else unset($data['password']);
        DB::table('providers')->where('id', $providerId)->update(array_merge($data, ['updated_at' => now()]));
        return back()->with('success', 'Provider updated successfully.');
    }

    public function destroyProvider(int $providerId)
    {
        DB::table('providers')->where('id', $providerId)->delete();
        return back()->with('success', 'Provider deleted successfully.');
    }

    public function updateRate(Request $request, int $rateId)
    {
        $data = $request->validate([
            'provider_id' => ['nullable', 'exists:providers,id'], 'customer_id' => ['nullable', 'exists:users,id'],
            'type' => ['required', 'string', 'max:50'], 'country' => ['nullable', 'string', 'max:80'],
            'prefix' => ['nullable', 'string', 'max:40'], 'buy_rate' => ['required', 'numeric', 'min:0'],
            'sell_rate' => ['required', 'numeric', 'min:0'], 'currency' => ['required', 'string', 'size:3'],
            'buy_currency' => ['required', 'string', 'size:3'], 'sell_currency' => ['required', 'string', 'size:3'],
        ]);
        DB::table('rates')->where('id', $rateId)->update(array_merge($data, ['updated_at' => now()]));
        return back()->with('success', 'Rate updated successfully.');
    }

    public function destroyRate(int $rateId)
    {
        DB::table('rates')->where('id', $rateId)->delete();
        return back()->with('success', 'Rate deleted successfully.');
    }

    public function updateRouting(Request $request, int $ruleId)
    {
        $data = $request->validate([
            'provider_id' => ['required', 'exists:providers,id'], 'customer_id' => ['nullable', 'exists:users,id'],
            'type' => ['required', 'string', 'max:50'], 'country' => ['nullable', 'string', 'max:80'],
            'prefix' => ['nullable', 'string', 'max:40'], 'strategy' => ['required', 'string', 'max:50'],
            'priority' => ['required', 'integer', 'min:1'], 'enabled' => ['required', 'boolean'],
        ]);
        DB::table('routing_rules')->where('id', $ruleId)->update(array_merge($data, ['updated_at' => now()]));
        return back()->with('success', 'Routing rule updated successfully.');
    }

    public function destroyRouting(int $ruleId)
    {
        DB::table('routing_rules')->where('id', $ruleId)->delete();
        return back()->with('success', 'Routing rule deleted successfully.');
    }

    public function reports(Request $request)
    {
        [$from, $to] = $this->reportRange($request);
        $filters = $this->reportFilters($request);
        $base = $this->reportBase($from, $to, $filters);
        $summary = (clone $base)->selectRaw('sms_messages.currency, COUNT(*) as total_sms, COALESCE(SUM(customer_charge),0) as revenue, COALESCE(SUM(provider_cost),0) as provider_cost, COALESCE(SUM(profit),0) as profit')->groupBy('sms_messages.currency')->orderBy('sms_messages.currency')->get();
        $clientBreakdown = (clone $base)->selectRaw("sms_messages.customer_id, COALESCE(users.name, 'API') as client_name, sms_messages.currency, COUNT(*) as total_sms, COALESCE(SUM(customer_charge),0) as revenue, COALESCE(SUM(provider_cost),0) as provider_cost, COALESCE(SUM(profit),0) as profit")->groupBy('sms_messages.customer_id', 'users.name', 'sms_messages.currency')->orderBy('client_name')->get();
        $providerBreakdown = (clone $base)->selectRaw("sms_messages.provider_id, COALESCE(providers.name, 'Unassigned') as provider_name, sms_messages.currency, COUNT(*) as total_sms, COALESCE(SUM(customer_charge),0) as revenue, COALESCE(SUM(provider_cost),0) as provider_cost, COALESCE(SUM(profit),0) as profit")->groupBy('sms_messages.provider_id', 'providers.name', 'sms_messages.currency')->orderBy('provider_name')->get();
        $status = (clone $base)->selectRaw('sms_messages.final_status, COUNT(*) as total')->groupBy('sms_messages.final_status')->orderBy('sms_messages.final_status')->pluck('total', 'final_status');
        $messages = (clone $base)->select('sms_messages.*', 'users.name as customer_name', 'providers.name as provider_name')->latest('sms_messages.id')->paginate(50)->withQueryString();
        $totalSms = $summary->sum('total_sms');
        $customers = DB::table('users')->whereIn('account_type', ['customer', 'reseller'])->orderBy('name')->get(['id', 'name']);
        $providers = DB::table('providers')->orderBy('name')->get(['id', 'name']);
        $currencies = DB::table('currencies')->where('enabled', true)->orderBy('code')->pluck('code');
        return view('admin.reports', compact('from', 'to', 'filters', 'summary', 'clientBreakdown', 'providerBreakdown', 'totalSms', 'status', 'messages', 'customers', 'providers', 'currencies'));
    }

    public function exportReports(Request $request)
    {
        [$from, $to] = $this->reportRange($request);
        $filters = $this->reportFilters($request);
        $rows = $this->reportBase($from, $to, $filters)->select('sms_messages.*', 'users.name as customer_name', 'providers.name as provider_name')->orderBy('sms_messages.id')->cursor();
        $filename = 'sms-profit-loss-' . $from->format('Ymd') . '-' . $to->format('Ymd') . ($filters['currency'] ? '-' . $filters['currency'] : '') . '.csv';
        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Message ID', 'Customer', 'Provider', 'Destination', 'Status', 'Segments', 'Sale Revenue', 'Provider Buy Cost', 'Profit', 'Currency', 'Created At', 'DLR At']);
            foreach ($rows as $row) fputcsv($out, [$row->message_id, $row->customer_name ?? 'API', $row->provider_name ?? 'Unassigned', $row->destination, $row->final_status, $row->segments, $row->customer_charge, $row->provider_cost, $row->profit, $row->currency, $row->created_at, $row->dlr_at]);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function reportRange(Request $request): array
    {
        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $from = Carbon::parse($data['from'] ?? now()->startOfMonth()->toDateString())->startOfDay();
        $to = Carbon::parse($data['to'] ?? now()->toDateString())->endOfDay();
        return [$from, $to];
    }

    private function reportFilters(Request $request): array
    {
        return $request->validate([
            'currency' => ['nullable', 'string', 'size:3'],
            'client_id' => ['nullable', 'integer', 'exists:users,id'],
            'provider_id' => ['nullable', 'integer', 'exists:providers,id'],
        ]);
    }

    private function reportBase(Carbon $from, Carbon $to, array $filters)
    {
        $query = DB::table('sms_messages')
            ->leftJoin('users', 'users.id', '=', 'sms_messages.customer_id')
            ->leftJoin('providers', 'providers.id', '=', 'sms_messages.provider_id')
            ->whereBetween('sms_messages.created_at', [$from, $to]);
        if (!empty($filters['currency'])) $query->where('sms_messages.currency', strtoupper($filters['currency']));
        if (!empty($filters['client_id'])) $query->where('sms_messages.customer_id', $filters['client_id']);
        if (!empty($filters['provider_id'])) $query->where('sms_messages.provider_id', $filters['provider_id']);
        return $query;
    }

    public function messages()
    {
        $messages = DB::table('sms_messages')->leftJoin('users', 'users.id', '=', 'sms_messages.customer_id')->select('sms_messages.*', 'users.name as customer_name')->latest('sms_messages.id')->paginate(30);
        return view('admin.messages', compact('messages'));
    }
}
