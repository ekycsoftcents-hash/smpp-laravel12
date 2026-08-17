<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvoiceController extends Controller
{
    public function index()
    {
        $invoices = DB::table('invoices as i')
            ->join('users as u', 'u.id', '=', 'i.customer_id')
            ->orderByDesc('i.issue_date')
            ->get(['i.*', 'u.name as customer_name', 'u.email']);
        $customers = DB::table('users')->whereIn('account_type', ['customer', 'reseller'])->orderBy('name')->get(['id', 'name', 'email', 'currency', 'balance']);
        $currencies = DB::table('currencies')->where('enabled', true)->orderBy('code')->get();
        return view('admin/invoices', compact('invoices', 'customers', 'currencies'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:users,id'],
            'currency' => ['required', 'string', 'size:3'],
            'description' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0.000001'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($data): void {
            $subtotal = round((float) $data['quantity'] * (float) $data['unit_price'], 6);
            $tax = round((float) ($data['tax'] ?? 0), 6);
            $total = $subtotal + $tax;
            $invoiceId = DB::table('invoices')->insertGetId([
                'customer_id' => $data['customer_id'],
                'invoice_number' => 'INV-' . now()->format('Ym') . '-' . strtoupper(Str::random(7)),
                'currency' => strtoupper($data['currency']),
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $total,
                'issue_date' => today(),
                'due_date' => $data['due_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'ISSUED',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('invoice_items')->insert([
                'invoice_id' => $invoiceId, 'description' => $data['description'],
                'quantity' => $data['quantity'], 'unit_price' => $data['unit_price'], 'amount' => $subtotal,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
        return back()->with('notice', 'Multi-currency invoice created successfully.');
    }

    public function payment(Request $request, int $invoiceId)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', 'string', 'max:40'],
            'reference' => ['required', 'string', 'max:120', 'unique:invoice_payments,reference'],
            'notes' => ['nullable', 'string'],
        ]);
        DB::transaction(function () use ($data, $invoiceId): void {
            $invoice = DB::table('invoices')->where('id', $invoiceId)->lockForUpdate()->firstOrFail();
            $amount = min((float) $data['amount'], max(0, (float) $invoice->total - (float) $invoice->paid));
            if ($amount <= 0) abort(422, 'Invoice is already fully paid.');
            DB::table('invoice_payments')->insert([
                'invoice_id' => $invoice->id, 'customer_id' => $invoice->customer_id,
                'reference' => $data['reference'], 'amount' => $amount, 'currency' => $invoice->currency,
                'method' => $data['method'], 'status' => 'POSTED', 'notes' => $data['notes'] ?? null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $paid = (float) $invoice->paid + $amount;
            DB::table('invoices')->where('id', $invoice->id)->update([
                'paid' => $paid, 'status' => $paid >= (float) $invoice->total ? 'PAID' : 'PARTIAL', 'updated_at' => now(),
            ]);
            DB::table('users')->where('id', $invoice->customer_id)->increment('balance', $amount);
        });
        return back()->with('notice', 'Payment posted and customer balance updated.');
    }
}
