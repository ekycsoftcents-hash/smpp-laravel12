<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\Jasmin\JasminSmppProvisioningService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function users()
    {
        return view('admin.users', ['users' => DB::table('users')->latest()->paginate(25)]);
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
        ]);
        $plainPassword = $data['password'];
        $smppPassword = $data['smpp_password'];
        $systemId = $data['system_id']; $maxBind = (int) $data['max_bind'];
        unset($data['smpp_password'], $data['system_id'], $data['max_bind']);
        $userId = DB::transaction(function () use ($data, $plainPassword, $smppPassword, $systemId, $maxBind, $jasmin) {
            $data['password'] = Hash::make($plainPassword);
            $data['email_verified_at'] = now();
            $userId = DB::table('users')->insertGetId(array_merge($data, ['balance' => 1.000000, 'created_at' => now(), 'updated_at' => now()]));
            $jasmin->provision('u' . $userId, $systemId, $smppPassword, $maxBind);
            DB::table('customer_smpp_accounts')->insert([
                'user_id' => $userId, 'system_id' => $systemId, 'password' => encrypt($smppPassword),
                'max_bind' => $maxBind, 'tps' => 1, 'enabled' => true, 'created_at' => now(), 'updated_at' => now(),
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

    public function updateUser(Request $request, int $userId)
    {
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
        ]);
        if (!empty($data['password'])) $data['password'] = Hash::make($data['password']);
        else unset($data['password']);
        unset($data['balance']);
        $balance = $request->input('balance');
        DB::table('users')->where('id', $userId)->update(array_merge($data, ['balance' => $balance, 'updated_at' => now()]));
        return back()->with('success', 'User updated successfully.');
    }

    public function destroyUser(int $userId, JasminSmppProvisioningService $jasmin)
    {
        $account = DB::table('customer_smpp_accounts')->where('user_id', $userId)->first();
        if ($account) {
            try { $jasmin->disable('u' . $userId); } catch (\Throwable $exception) { report($exception); }
            DB::table('customer_smpp_accounts')->where('user_id', $userId)->delete();
        }
        DB::table('users')->where('id', $userId)->delete();
        return back()->with('success', 'User deleted successfully.');
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

    public function reports()
    {
        return $this->messages();
    }

    public function messages()
    {
        $messages = DB::table('sms_messages')->leftJoin('users', 'users.id', '=', 'sms_messages.customer_id')->select('sms_messages.*', 'users.name as customer_name')->latest('sms_messages.id')->paginate(30);
        return view('admin.messages', compact('messages'));
    }
}
