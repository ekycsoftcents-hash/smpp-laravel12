<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'REVE SMS Control Plane' }}</title>
    <style>
        :root{--bg:#f4f7fb;--panel:#fff;--nav:#0878bd;--nav-dark:#075d95;--text:#18324b;--muted:#6f8498;--line:#dce6ef;--accent:#f39a19;--good:#15966d;--warn:#bc7712;--danger:#d94f61;--shadow:0 8px 24px rgba(27,67,104,.08)}
        *{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font:14px Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}a{color:inherit;text-decoration:none}.shell{display:flex;min-height:100vh}.side{width:268px;background:linear-gradient(180deg,var(--nav),var(--nav-dark));color:#fff;position:fixed;inset:0 auto 0 0;overflow:auto;box-shadow:4px 0 16px rgba(0,0,0,.08)}.brand{padding:20px 20px 18px;font-size:19px;font-weight:800;letter-spacing:.2px;border-bottom:1px solid rgba(255,255,255,.18)}.brand span{color:#ffd27e}.section{padding:18px 20px 7px;color:#b9ddf1;text-transform:uppercase;font-size:10px;font-weight:800;letter-spacing:1.35px}.nav a{display:flex;align-items:center;gap:9px;padding:10px 20px;color:#e4f3fb;font-size:13px;border-left:3px solid transparent}.nav a:hover,.nav a.active{background:rgba(0,0,0,.16);border-left-color:var(--accent);color:#fff}.nav a.muted{opacity:.6;cursor:not-allowed}.main{margin-left:268px;width:calc(100% - 268px);padding:28px 34px;min-width:0}.top{display:flex;align-items:center;justify-content:space-between;margin-bottom:25px}.eyebrow{color:var(--nav);font-size:11px;text-transform:uppercase;letter-spacing:1.5px;font-weight:800}.h1{font-size:28px;font-weight:800;margin:5px 0}.sub,.label{color:var(--muted)}.pill{border:1px solid #b9e5d6;background:#e9faf4;color:var(--good);padding:7px 10px;border-radius:99px;font-size:12px}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:15px}.card{background:var(--panel);border:1px solid var(--line);border-radius:10px;padding:18px;box-shadow:var(--shadow)}.label{font-size:12px}.value{font-size:27px;font-weight:800;margin-top:8px}.good{color:var(--good)}.warn{color:var(--warn)}.bad{color:var(--danger)}.split{display:grid;grid-template-columns:1.4fr 1fr;gap:15px;margin-top:15px}.card h2{font-size:15px;margin:0 0 15px}.row{display:flex;justify-content:space-between;padding:11px 0;border-bottom:1px solid var(--line);color:#49647a}.row:last-child{border:0}.status{font-size:11px;padding:4px 7px;border-radius:5px;background:#e9faf4;color:var(--good)}.modules{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}.module{background:#f7fbfe;border:1px solid #dcecf5;padding:13px;border-radius:9px;color:#24516f}.module small{display:block;color:var(--muted);margin-top:5px}.button{display:inline-block;background:var(--nav);color:#fff;padding:9px 14px;border:0;border-radius:7px;font-weight:800;cursor:pointer}.button:hover{background:var(--nav-dark)}.formgrid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:16px}label{display:flex;flex-direction:column;gap:6px;color:var(--muted);font-size:12px}input,select{background:#fff;color:var(--text);border:1px solid #cbd9e4;border-radius:6px;padding:10px}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:11px 9px;border-bottom:1px solid var(--line);white-space:nowrap}th{color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.5px;background:#f8fbfd}.tablewrap{overflow:auto}.tag{display:inline-block;padding:4px 7px;border-radius:5px;background:#e9faf4;color:var(--good);font-size:11px}.notice{background:#e9faf4;color:#147752;border:1px solid #b9e5d6;padding:11px;border-radius:8px;margin-bottom:15px}.error{background:#fff0f2;color:#ad3547;border:1px solid #efb8c1;padding:11px;border-radius:8px;margin-bottom:15px}.sub a,.card a{color:var(--nav)}code{color:#235b86}.loggrid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.loggrid h3{font-size:12px;color:var(--muted)}pre{background:#f7fafc;border:1px solid var(--line);border-radius:8px;padding:12px;max-height:360px;overflow:auto;white-space:pre-wrap;color:#36526b;font:11px ui-monospace,monospace}@media(max-width:1000px){.side{width:220px}.main{margin-left:220px;width:calc(100% - 220px);padding:22px}.grid{grid-template-columns:repeat(2,1fr)}.split{grid-template-columns:1fr}.modules{grid-template-columns:repeat(2,1fr)}}@media(max-width:700px){.side{position:static;width:100%;min-height:auto}.shell{display:block}.main{margin-left:0;width:100%;padding:16px}.formgrid{grid-template-columns:1fr}.grid{grid-template-columns:1fr}.modules{grid-template-columns:1fr}}
    </style>
</head>
<body>
<div class="shell">
    <aside class="side">
        <div class="brand"><span>◆</span> REVE SMS <small style="display:block;font-size:10px;font-weight:500;opacity:.78;margin-top:4px">SMPP Control Plane</small></div>
        <nav class="nav">
            <div class="section">Overview</div>
            <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">Dashboard</a>
            <a class="{{ request()->routeIs('admin.monitoring*') ? 'active' : '' }}" href="{{ route('admin.monitoring') }}">Live Report</a>
            <a class="{{ request()->routeIs('admin.reports*') ? 'active' : '' }}" href="{{ route('admin.reports') }}">General Reports</a>

            <div class="section">Client</div>
            <a class="{{ request()->routeIs('admin.users') ? 'active' : '' }}" href="{{ route('admin.users') }}">SMS Clients</a>
            <a class="muted" href="#" title="Client hierarchy workflow is planned">Client Hierarchy</a>
            <a class="muted" href="#" title="HTTP profile workflow is planned">HTTP Profiles</a>
            <a class="{{ request()->routeIs('admin.users') ? 'active' : '' }}" href="{{ route('admin.users') }}">SMPP Profiles</a>

            <div class="section">Messaging Controls</div>
            <a class="muted" href="#" title="Contact workflow is planned">SMS Contacts</a>
            <a class="muted" href="#" title="SenderID translation workflow is planned">SenderID Translation</a>
            <a class="muted" href="#" title="Text translation workflow is planned">Text Translation</a>
            <a class="muted" href="#" title="Content whitelist workflow is planned">Content Whitelisting</a>
            <a class="muted" href="#" title="Blocking workflow is planned">Text/SenderID Blocking</a>

            <div class="section">Rates and Destinations</div>
            <a class="{{ request()->routeIs('admin.rates') ? 'active' : '' }}" href="{{ route('admin.rates') }}">SMS Rate Plans</a>
            <a class="muted" href="#" title="Country workflow is planned">SMS Countries</a>

            <div class="section">Route Management</div>
            <a class="{{ request()->routeIs('admin.routing') ? 'active' : '' }}" href="{{ route('admin.routing') }}">SMS Routes</a>
            <a class="{{ request()->routeIs('admin.providers') ? 'active' : '' }}" href="{{ route('admin.providers') }}">SMPP Providers</a>
            <a class="muted" href="#" title="SMS translation workflow is planned">SMS Translations</a>

            <div class="section">Campaign Management</div>
            <a class="muted" href="#" title="Campaign workflow is planned">SMS Campaigns</a>
            <a class="muted" href="#" title="SMS mask workflow is planned">SMS Masks</a>
            <a class="muted" href="#" title="Pending approval workflow is planned">Pending Requests</a>

            <div class="section">Finance and Accounts</div>
            <a class="{{ request()->routeIs('admin.currencies') ? 'active' : '' }}" href="{{ route('admin.currencies') }}">Currency & Exchange Rates</a>
            <a class="{{ request()->routeIs('admin.reports') ? 'active' : '' }}" href="{{ route('admin.reports') }}">Profit Summary</a>
            <a class="muted" href="#" title="Invoice workflow is planned">Invoices & Recharge</a>
            <a class="muted" href="#" title="Ledger view is planned">Billing Ledger</a>

            <div class="section">Logs and Security</div>
            <a class="{{ request()->routeIs('admin.messages') ? 'active' : '' }}" href="{{ route('admin.messages') }}">SMS History / CDR</a>
            <a class="muted" href="#" title="Activity log workflow is planned">General Activity Log</a>
            <a class="muted" href="#" title="Security workflow is planned">Restricted IPs</a>
            <a class="muted" href="#" title="Access log workflow is planned">Unauthorized Access Log</a>
            <a class="muted" href="#" title="Login security workflow is planned">Active Logins</a>

            <div class="section">Settings</div>
            <a class="muted" href="#" title="System configuration workflow is planned">System Configuration</a>
            <a class="muted" href="#" title="Payment configuration workflow is planned">Payment Gateway</a>
            <a class="muted" href="#" title="Mail configuration workflow is planned">Mail Server</a>
            <a class="muted" href="#" title="Alert configuration workflow is planned">System Alerts</a>
        </nav>
    </aside>
    <main class="main">@yield('content')</main>
</div>
</body>
</html>
