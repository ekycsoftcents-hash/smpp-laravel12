<?php
namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;
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
    public function health() { return response()->json(['status'=>'ok','service'=>'smpp-control-plane','laravel'=>app()->version()]); }
}
