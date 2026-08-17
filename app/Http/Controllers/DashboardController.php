<?php
namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\View\View;
class DashboardController extends Controller {
    public function index(): View {
        $stats = [
            'total_sms' => DB::table('sms_messages')->count(),
            'submitted' => DB::table('sms_messages')->where('final_status', 'SUBMITTED')->count(),
            'delivered' => DB::table('sms_messages')->where('final_status', 'DELIVERED')->count(),
            'failed' => DB::table('sms_messages')->whereIn('final_status', ['FAILED','REJECTED','EXPIRED'])->count(),
            'pending_dlr' => DB::table('sms_messages')->whereIn('final_status', ['SUBMITTED','UNKNOWN'])->count(),
            'customers' => DB::table('users')->where('account_type', 'customer')->count(),
            'providers' => DB::table('providers')->count(),
        ];
        return view('dashboard', compact('stats'));
    }
    public function live()
    {
        $now = now();
        $window = $now->copy()->subMinute();
        $recent = DB::table('sms_messages')->where('created_at', '>=', $window);
        $lowBalance = DB::table('users as u')
            ->leftJoin('customer_smpp_accounts as a', 'a.user_id', '=', 'u.id')
            ->whereIn('u.account_type', ['customer', 'reseller'])
            ->where('u.balance', '<', 1.00)
            ->orderBy('u.balance')
            ->get(['u.id', 'u.name', 'u.email', 'u.balance', 'u.currency', 'a.system_id', 'a.enabled']);

        return response()->json([
            'generated_at' => $now->toIso8601String(),
            'traffic' => [
                'last_minute' => $recent->count(),
                'submitted_per_minute' => (clone $recent)->where('final_status', 'SUBMITTED')->count(),
                'delivered_per_minute' => (clone $recent)->where('final_status', 'DELIVERED')->count(),
                'failed_per_minute' => (clone $recent)->whereIn('final_status', ['FAILED', 'REJECTED', 'EXPIRED'])->count(),
                'pending_dlr' => DB::table('sms_messages')->whereIn('final_status', ['SUBMITTED', 'UNKNOWN'])->count(),
                'queue_backlog' => (int) Redis::llen('queues:sms-submit'),
            ],
            'alerts' => [
                'low_balance_threshold' => 1.00,
                'low_balance_customers' => $lowBalance,
                'low_balance_count' => $lowBalance->count(),
            ],
        ]);
    }

    public function health() { return response()->json(['status'=>'ok','service'=>'smpp-control-plane','laravel'=>app()->version()]); }
}
