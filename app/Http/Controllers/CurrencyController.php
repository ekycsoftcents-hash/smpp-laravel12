<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CurrencyController extends Controller
{
    public function index()
    {
        return view('admin.currencies', [
            'currencies' => DB::table('currencies')->orderBy('code')->get(),
            'rates' => DB::table('exchange_rates')->latest('effective_at')->limit(100)->get(),
        ]);
    }

    public function storeCurrency(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'size:3', 'unique:currencies,code'],
            'name' => ['required', 'string', 'max:80'],
            'symbol' => ['nullable', 'string', 'max:8'],
            'minor_unit' => ['required', 'integer', 'between:0,6'],
            'is_base' => ['nullable', 'boolean'],
        ]);
        $data['code'] = strtoupper($data['code']);
        $data['is_base'] = (bool) ($data['is_base'] ?? false);
        if ($data['is_base']) DB::table('currencies')->update(['is_base' => false]);
        DB::table('currencies')->insert(array_merge($data, ['enabled' => true, 'created_at' => now(), 'updated_at' => now()]));
        return back()->with('success', 'Currency added.');
    }

    public function updateCurrency(Request $request, int $currencyId)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'size:3', Rule::unique('currencies', 'code')->ignore($currencyId)],
            'name' => ['required', 'string', 'max:80'], 'symbol' => ['nullable', 'string', 'max:8'],
            'minor_unit' => ['required', 'integer', 'between:0,6'], 'is_base' => ['nullable', 'boolean'],
            'enabled' => ['required', 'boolean'],
        ]);
        $data['code'] = strtoupper($data['code']);
        $data['is_base'] = (bool) $data['is_base'];
        if ($data['is_base']) DB::table('currencies')->where('id', '!=', $currencyId)->update(['is_base' => false]);
        DB::table('currencies')->where('id', $currencyId)->update(array_merge($data, ['updated_at' => now()]));
        return back()->with('success', 'Currency updated.');
    }

    public function destroyCurrency(int $currencyId)
    {
        DB::table('currencies')->where('id', $currencyId)->update(['enabled' => false, 'updated_at' => now()]);
        return back()->with('success', 'Currency disabled.');
    }

    public function updateRate(Request $request, int $rateId)
    {
        $data = $request->validate([
            'base_currency' => ['required', 'exists:currencies,code'],
            'quote_currency' => ['required', 'exists:currencies,code', 'different:base_currency'],
            'rate' => ['required', 'numeric', 'gt:0'], 'expires_at' => ['nullable', 'date'],
            'enabled' => ['required', 'boolean'],
        ]);
        $data['base_currency'] = strtoupper($data['base_currency']);
        $data['quote_currency'] = strtoupper($data['quote_currency']);
        DB::table('exchange_rates')->where('id', $rateId)->update(array_merge($data, ['updated_at' => now()]));
        return back()->with('success', 'Exchange rate updated.');
    }

    public function destroyRate(int $rateId)
    {
        DB::table('exchange_rates')->where('id', $rateId)->update(['enabled' => false, 'updated_at' => now()]);
        return back()->with('success', 'Exchange rate disabled.');
    }

    public function storeRate(Request $request)
    {
        $data = $request->validate([
            'base_currency' => ['required', 'exists:currencies,code'],
            'quote_currency' => ['required', 'exists:currencies,code', 'different:base_currency'],
            'rate' => ['required', 'numeric', 'gt:0'],
            'expires_at' => ['nullable', 'date', 'after:effective_at'],
        ]);
        $data['base_currency'] = strtoupper($data['base_currency']);
        $data['quote_currency'] = strtoupper($data['quote_currency']);
        $data['effective_at'] = now();
        $data['source'] = 'admin'; $data['enabled'] = true;
        DB::table('exchange_rates')->insert(array_merge($data, ['created_at' => now(), 'updated_at' => now()]));
        return back()->with('success', 'Exchange rate added.');
    }
}
