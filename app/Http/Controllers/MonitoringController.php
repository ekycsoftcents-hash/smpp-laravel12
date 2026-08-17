<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class MonitoringController extends Controller
{
    public function live()
    {
        $metrics = [];
        $online = false;
        try {
            $base = rtrim(str_replace('/send', '', config('smpp.jasmin_http_url', 'http://jasmin:1401')), '/');
            $response = Http::timeout(3)->get($base . '/metrics');
            $online = $response->successful();
            foreach (preg_split('/\r?\n/', $response->body()) as $line) {
                if (preg_match('/^(smppsapi_[a-z0-9_]+)\s+([0-9.]+)/', trim($line), $match)) {
                    $metrics[$match[1]] = (float) $match[2];
                }
            }
        } catch (\Throwable $exception) {
            $online = false;
        }

        $accounts = DB::table('customer_smpp_accounts as a')
            ->join('users as u', 'u.id', '=', 'a.user_id')
            ->orderBy('u.name')
            ->get(['a.user_id', 'a.system_id', 'a.max_bind', 'a.tps', 'a.enabled', 'u.name', 'u.email']);

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'jasmin_online' => $online,
            'global' => [
                'live_connections' => (int) ($metrics['smppsapi_connected_count'] ?? 0),
                'bound_transceivers' => (int) ($metrics['smppsapi_bound_trx_count'] ?? 0),
                'bound_receivers' => (int) ($metrics['smppsapi_bound_rx_count'] ?? 0),
                'bound_transmitters' => (int) ($metrics['smppsapi_bound_tx_count'] ?? 0),
                'submit_sm_performed' => (int) ($metrics['smppsapi_submit_sm_count'] ?? 0),
                'throttling_errors' => (int) ($metrics['smppsapi_throttling_error_count'] ?? 0),
            ],
            'accounts' => $accounts,
        ]);
    }

    public function index()
    {
        $jasminOnline = false;
        try {
            $jasminOnline = Http::timeout(3)->get(config('smpp.jasmin_http_url', 'http://jasmin:1401') . '/ping')->successful();
        } catch (\Throwable $e) {
            $jasminOnline = false;
        }

        $smppUsers = DB::table('customer_smpp_accounts')
            ->join('users', 'users.id', '=', 'customer_smpp_accounts.user_id')
            ->select('customer_smpp_accounts.*', 'users.name', 'users.email')
            ->orderBy('users.name')->get();
        $providers = DB::table('providers')->orderBy('priority')->orderBy('name')->get();
        $clientActivity = DB::table('sms_messages')->latest('sms_messages.created_at')->limit(50)->get();
        $logFiles = glob('/var/log/jasmin/*.log') ?: [];
        $logs = [];
        foreach ($logFiles as $file) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            $logs[basename($file)] = array_slice(array_reverse($lines), 0, 80);
        }

        return view('admin.monitoring', compact('jasminOnline', 'smppUsers', 'providers', 'clientActivity', 'logs'));
    }
}
