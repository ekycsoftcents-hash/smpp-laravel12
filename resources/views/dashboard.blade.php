@extends('layouts.app')
@section('content')<div class="top"><div><div class="eyebrow">Operations cockpit</div><div class="h1">SMS traffic overview</div><div class="sub">Laravel {{ app()->version() }} · Native Node.js SMPP Gateway · PostgreSQL ledger</div></div><div class="pill">● System nominal</div></div><div class="grid">@foreach ([['Total SMS','total_sms',''],['Submitted','submitted','warn'],['Delivered','delivered','good'],['Failed','failed','bad'],['Pending DLR','pending_dlr','warn'],['Customers','customers',''],['Providers','providers','good']] as [$label,$key,$tone])<div class="card"><div class="label">{{ $label }}</div><div class="value {{ $tone }}">{{ number_format($stats[$key]) }}</div></div>@endforeach<div class="card"><div class="label">Delivery rate</div><div class="value good">—</div></div></div><section class="card" style="margin-top:15px"><div class="top" style="margin-bottom:12px"><h2 style="margin:0">Live SMS traffic</h2><span id="live-updated" class="status">Connecting…</span></div><div class="grid" style="grid-template-columns:repeat(6,minmax(0,1fr))"><div><div class="label">Last minute</div><div id="live-total" class="value">—</div></div><div><div class="label">Submitted/min</div><div id="live-submitted" class="value warn">—</div></div><div><div class="label">Delivered/min</div><div id="live-delivered" class="value good">—</div></div><div><div class="label">Failed/min</div><div id="live-failed" class="value bad">—</div></div><div><div class="label">Pending DLR</div><div id="live-pending" class="value warn">—</div></div><div><div class="label">Queue backlog</div><div id="live-backlog" class="value">—</div></div></div></section><section id="balance-alerts" class="card" style="margin-top:15px;display:none;background:#401d27;border-color:#71303d"><h2>Balance alerts: below 1.00</h2><div id="balance-alert-list"></div></section><div class="split"><section class="card"><h2>Provider health</h2><div class="row"><span>Native SMPP gateway</span><span class="status">CONNECTED</span></div><div class="row"><span>Redis queue</span><span class="status">READY</span></div><div class="row"><span>RabbitMQ broker</span><span class="status">READY</span></div><div class="row"><span>PostgreSQL</span><span class="status">READY</span></div></section><section class="card"><h2>Business controls</h2><div class="row"><span>Today's revenue</span><strong>৳ 0.00</strong></div><div class="row"><span>Provider cost</span><strong>৳ 0.00</strong></div><div class="row"><span>Gross profit</span><strong class="good">৳ 0.00</strong></div><div class="row"><span>Pending ledger entries</span><strong>0</strong></div></section></div><section class="card" style="margin-top:15px"><h2>Product areas</h2><div class="modules"><a class="module" href="{{ route('admin.users') }}">Identity & hierarchy<small>Admin · customer · reseller · roles</small></a><a class="module" href="{{ route('admin.providers') }}">Provider management<small>SMPP credentials · health · failover</small></a><a class="module" href="{{ route('admin.routing') }}">Routing engine<small>Prefix · country · quality · LCR</small></a><a class="module" href="{{ route('admin.rates') }}">Dual-side billing<small>Submission/DLR · buy/sell rates</small></a><a class="module" href="{{ route('admin.messages') }}">DLR & refund engine<small>Delivered · failed · expired policies</small></a><a class="module" href="{{ route('admin.reports') }}">Monitoring & reports<small>TPS · queue · provider status</small></a></div></section><script>
(() => {
    const number = value => Number(value || 0).toLocaleString();
    const updateLive = async () => {
        try {
            const response = await fetch('{{ route('dashboard.live') }}', {headers: {'Accept': 'application/json'}, cache: 'no-store'});
            if (!response.ok) throw new Error('HTTP ' + response.status);
            const data = await response.json();
            const traffic = data.traffic || {};
            document.getElementById('live-total').textContent = number(traffic.last_minute);
            document.getElementById('live-submitted').textContent = number(traffic.submitted_per_minute);
            document.getElementById('live-delivered').textContent = number(traffic.delivered_per_minute);
            document.getElementById('live-failed').textContent = number(traffic.failed_per_minute);
            document.getElementById('live-pending').textContent = number(traffic.pending_dlr);
            document.getElementById('live-backlog').textContent = number(traffic.queue_backlog);
            document.getElementById('live-updated').textContent = 'Updated ' + new Date(data.generated_at).toLocaleTimeString();
            const customers = (data.alerts || {}).low_balance_customers || [];
            const alertBox = document.getElementById('balance-alerts');
            const list = document.getElementById('balance-alert-list');
            alertBox.style.display = customers.length ? 'block' : 'none';
            list.innerHTML = customers.map(customer => `<div class="row"><span>${customer.name} (${customer.system_id || 'no SMPP ID'})</span><strong>${Number(customer.balance).toFixed(6)} ${customer.currency || ''}</strong></div>`).join('');
            const seen = JSON.parse(localStorage.getItem('smpp-low-balance-alerts') || '{}');
            customers.forEach(customer => {
                if (seen[customer.id]) return;
                seen[customer.id] = Date.now();
                if ('Notification' in window && Notification.permission === 'granted') new Notification('SMPP balance alert', {body: `${customer.name} is below 1.00 ${customer.currency || ''}.`});
            });
            localStorage.setItem('smpp-low-balance-alerts', JSON.stringify(seen));
        } catch (error) {
            document.getElementById('live-updated').textContent = 'Offline';
        }
    };
    if ('Notification' in window && Notification.permission === 'default') document.addEventListener('click', () => Notification.requestPermission(), {once: true});
    updateLive();
    setInterval(updateLive, 5000);
})();
</script>
@endsection
