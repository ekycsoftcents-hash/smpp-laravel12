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
            $response = Http::timeout(3)->get(rtrim(config('smpp.gateway.url'), '/') . '/health');
            $online = $response->successful();
            $gateway = $response->json();
            $metrics['gateway_provider_count'] = count($gateway['providers'] ?? []);
            $metrics['gateway_bound_provider_count'] = collect($gateway['providers'] ?? [])->where('state', 'BOUND')->count();
        } catch (\Throwable $exception) {
            $online = false;
        }

        $accounts = DB::table('customer_smpp_accounts as a')
            ->join('users as u', 'u.id', '=', 'a.user_id')
            ->orderBy('u.name')
            ->get(['a.user_id', 'a.system_id', 'a.max_bind', 'a.tps', 'a.current_binds', 'a.last_bind_at', 'a.last_unbind_at', 'a.enabled', 'u.name', 'u.email']);

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'gateway_online' => $online,
            'global' => [
                'live_connections' => (int) ($metrics['gateway_bound_provider_count'] ?? 0),
                'bound_transceivers' => (int) ($metrics['gateway_bound_provider_count'] ?? 0),
                'bound_receivers' => 0,
                'bound_transmitters' => 0,
                'submit_sm_performed' => (int) DB::table('sms_messages')->whereDate('created_at', today())->count(),
                'throttling_errors' => (int) DB::table('sms_messages')->whereDate('created_at', today())->where('provider_status', 'THROTTLED')->count(),
            ],
            'accounts' => $accounts,
        ]);
    }

    public function providerLive()
    {
        $providers = DB::table('providers')->orderBy('priority')->orderBy('name')->get();
        $result = [];
        foreach ($providers as $provider) {
            $started = microtime(true); $errno = 0; $error = '';
            $socket = @fsockopen($provider->host, (int) $provider->port, $errno, $error, 2);
            $online = is_resource($socket);
            if ($online) fclose($socket);
            $latency = round((microtime(true) - $started) * 1000, 2);
            $healthError = $online ? null : ($error ?: 'Connection refused or timed out');
            DB::table('providers')->where('id', $provider->id)->update([
                'status' => $online ? 'CONNECTED' : 'DISCONNECTED',
                'last_health_at' => now(),
                'health_error' => $healthError,
                'health_latency_ms' => $latency,
                'updated_at' => now(),
            ]);
            $logs = DB::table('sms_messages')->where('provider_id', $provider->id)->latest('created_at')->limit(15)->get(['id', 'message_id', 'destination', 'final_status', 'provider_status', 'created_at', 'updated_at']);
            $result[] = [
                'id' => $provider->id,
                'name' => $provider->name,
                'host' => $provider->host,
                'port' => $provider->port,
                'status' => $online ? 'CONNECTED' : 'DISCONNECTED',
                'latency_ms' => $latency,
                'error' => $healthError,
                'logs' => $logs,
            ];
        }
        return response()->json(['generated_at' => now()->toIso8601String(), 'providers' => $result]);
    }

    public function index()
    {
        $gatewayOnline = false;
        try {
            $gatewayOnline = Http::timeout(3)->get(rtrim(config('smpp.gateway.url'), '/') . '/health')->successful();
        } catch (\Throwable $e) {
            $gatewayOnline = false;
        }

        $smppUsers = DB::table('customer_smpp_accounts')
            ->join('users', 'users.id', '=', 'customer_smpp_accounts.user_id')
            ->select('customer_smpp_accounts.*', 'users.name', 'users.email')
            ->orderBy('users.name')->get();
        $providers = DB::table('providers')->orderBy('priority')->orderBy('name')->get();
        $clientActivity = DB::table('sms_messages')->latest('sms_messages.created_at')->limit(50)->get();
        $logFiles = glob(storage_path('logs/*.log')) ?: [];
        $logs = [];
        foreach ($logFiles as $file) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            $logs[basename($file)] = array_slice(array_reverse($lines), 0, 80);
        }

        return view('admin.monitoring', compact('gatewayOnline', 'smppUsers', 'providers', 'clientActivity', 'logs'));
    }
}
