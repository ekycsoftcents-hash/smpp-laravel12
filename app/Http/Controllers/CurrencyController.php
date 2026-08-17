<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
