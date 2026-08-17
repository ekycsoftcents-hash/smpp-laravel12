<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function users()
    {
        return view('admin.users', ['users' => DB::table('users')->latest()->paginate(25)]);
    }

    public function storeUser(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'account_type' => ['required', 'in:admin,customer,reseller,operator'],
            'role' => ['required', 'string', 'max:80'],
            'customer_billing_mode' => ['required', 'in:SUBMISSION,DLR'],
            'provider_billing_mode' => ['required', 'in:SUBMISSION,DLR'],
        ]);
        $data['password'] = Hash::make($data['password']);
        $data['email_verified_at'] = now();
        DB::table('users')->insert(array_merge($data, ['created_at' => now(), 'updated_at' => now()]));
        return back()->with('success', 'User created successfully.');
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
        ]);
        DB::table('rates')->insert(array_merge($data, ['effective_from' => now(), 'created_at' => now(), 'updated_at' => now()]));
        return back()->with('success', 'Rate created successfully.');
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

    public function messages()
    {
        $messages = DB::table('sms_messages')->leftJoin('users', 'users.id', '=', 'sms_messages.customer_id')->select('sms_messages.*', 'users.name as customer_name')->latest('sms_messages.id')->paginate(30);
        return view('admin.messages', compact('messages'));
    }
}
