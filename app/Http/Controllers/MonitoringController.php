<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class MonitoringController extends Controller
{
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
