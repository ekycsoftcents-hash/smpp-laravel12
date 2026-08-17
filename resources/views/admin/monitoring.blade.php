@extends('layouts.app')
@section('content')
<div class="top"><div><div class="eyebrow">Operations / Monitoring</div><div class="h1">SMPP Monitor</div><div class="sub">Customer binds, provider health, client activity and gateway logs.</div></div><div><span class="pill">{{ $gatewayOnline ? '● Gateway ONLINE' : '● Gateway OFFLINE' }}</span> <a class="button" href="{{ route('admin.monitoring') }}">Refresh</a></div></div>
<x-live-traffic :events-url="config('smpp.gateway.events_url')" />
<div id="live-traffic-react" data-events-url="{{ config('smpp.gateway.events_url') }}"></div>
@vite('resources/js/live-traffic-react.jsx')
<section class="card" style="margin-top:15px"><div class="top" style="margin-bottom:12px"><h2 style="margin:0">Live SMPP bind monitor</h2><span id="smpp-live-updated" class="status">Connecting…</span></div><div class="grid" style="grid-template-columns:repeat(6,minmax(0,1fr))"><div><div class="label">Live connections</div><div id="smpp-live-connections" class="value good">—</div></div><div><div class="label">Transceiver binds</div><div id="smpp-live-trx" class="value">—</div></div><div><div class="label">Receiver binds</div><div id="smpp-live-rx" class="value">—</div></div><div><div class="label">Transmitter binds</div><div id="smpp-live-tx" class="value">—</div></div><div><div class="label">SubmitSm total</div><div id="smpp-live-submit" class="value">—</div></div><div><div class="label">Throttle errors</div><div id="smpp-live-throttle" class="value warn">—</div></div></div></section><div class="grid"><div class="card"><div class="label">Native SMPP Gateway</div><div class="value {{ $gatewayOnline ? 'good' : 'bad' }}">{{ $gatewayOnline ? 'ONLINE' : 'OFFLINE' }}</div></div><div class="card"><div class="label">SMPP accounts</div><div class="value">{{ $smppUsers->count() }}</div></div><div class="card"><div class="label">Providers</div><div class="value">{{ $providers->count() }}</div></div><div class="card"><div class="label">Client records</div><div class="value">{{ $clientActivity->count() }}</div></div></div>
<section class="card" style="margin-top:15px"><h2>Customer SMPP user status</h2><div class="sub" style="margin-bottom:12px">Account enabled state is shown here; live bind events are published by the native Node.js gateway and reflected through the live monitor endpoint.</div><div class="tablewrap"><table><thead><tr><th>User</th><th>System ID</th><th>Max bind</th><th>TPS</th><th>Enabled</th><th>Observed status</th></tr></thead><tbody>@forelse($smppUsers as $user)<tr><td>{{ $user->name }}<br><small>{{ $user->email }}</small></td><td>{{ $user->system_id }}</td><td>{{ $user->max_bind }}</td><td>{{ $user->tps }}</td><td><span class="tag">{{ $user->enabled ? 'ENABLED' : 'DISABLED' }}</span></td><td><span class="tag">{{ $user->enabled ? 'READY / CHECK LOG' : 'BLOCKED' }}</span></td></tr>@empty<tr><td colspan="6">No customer SMPP accounts configured yet.</td></tr>@endforelse</tbody></table></div></section>
<section class="card" style="margin-top:15px"><h2>Provider connection status</h2><div class="tablewrap"><table><thead><tr><th>Provider</th><th>Host</th><th>Port</th><th>Status</th><th>Last health</th><th>Priority</th></tr></thead><tbody>@forelse($providers as $provider)<tr><td>{{ $provider->name }}</td><td>{{ $provider->host }}</td><td>{{ $provider->port }}</td><td><span class="tag">{{ $provider->status }}</span></td><td>{{ $provider->last_health_at ?? 'Not checked' }}</td><td>{{ $provider->priority }}</td></tr>@empty<tr><td colspan="6">No providers configured yet.</td></tr>@endforelse</tbody></table></div></section>
<section class="card" style="margin-top:15px"><div class="top"><h2 style="margin:0">Provider status and logs</h2><span id="provider-live-updated" class="status">Connecting…</span></div><div id="provider-live-panels" class="loggrid" style="margin-top:12px"><div class="sub">Loading provider status…</div></div></section><section class="card" style="margin-top:15px"><h2>Client SMS activity</h2><div class="tablewrap"><table><thead><tr><th>Created</th><th>Message ID</th><th>Source</th><th>Destination</th><th>Status</th><th>Charge</th></tr></thead><tbody>@forelse($clientActivity as $item)<tr><td>{{ $item->created_at }}</td><td><code>{{ \Illuminate\Support\Str::limit($item->message_id, 14) }}</code></td><td>{{ $item->source }}</td><td>{{ $item->destination }}</td><td><span class="tag">{{ $item->final_status }}</span></td><td>{{ number_format($item->customer_charge, 4) }}</td></tr>@empty<tr><td colspan="6">No client SMS activity yet.</td></tr>@endforelse</tbody></table></div></section>
<section class="card" style="margin-top:15px"><h2>Server / Gateway logs</h2><div class="loggrid">@forelse($logs as $name => $lines)<div><h3>{{ $name }}</h3><pre>@foreach($lines as $line){{ $line }}
@endforeach</pre></div>@empty<div class="sub">No gateway log files found yet. Use `docker compose logs gateway` for container stdout.</div>@endforelse</div></section>
<script>
(() => {
    const ids = ['smpp-live-connections','smpp-live-trx','smpp-live-rx','smpp-live-tx','smpp-live-submit','smpp-live-throttle'];
    const refresh = async () => {
        try {
            const response = await fetch('{{ route('admin.monitoring.live') }}', {headers: {'Accept': 'application/json'}, cache: 'no-store'});
            if (!response.ok) throw new Error('HTTP ' + response.status);
            const data = await response.json();
            const global = data.global || {};
            document.getElementById('smpp-live-connections').textContent = global.live_connections ?? 0;
            document.getElementById('smpp-live-trx').textContent = global.bound_transceivers ?? 0;
            document.getElementById('smpp-live-rx').textContent = global.bound_receivers ?? 0;
            document.getElementById('smpp-live-tx').textContent = global.bound_transmitters ?? 0;
            document.getElementById('smpp-live-submit').textContent = global.submit_sm_performed ?? 0;
            document.getElementById('smpp-live-throttle').textContent = global.throttling_errors ?? 0;
            document.getElementById('smpp-live-updated').textContent = (data.gateway_online ? 'Updated ' : 'Gateway offline · ') + new Date(data.generated_at).toLocaleTimeString();
        } catch (error) {
            ids.forEach(id => document.getElementById(id).textContent = '—');
            document.getElementById('smpp-live-updated').textContent = 'Offline';
        }
    };
    refresh();
    setInterval(refresh, 5000);
})();
const refreshProviders = async () => {
        try {
            const response = await fetch('{{ route('admin.monitoring.providers.live') }}', {headers: {'Accept': 'application/json'}, cache: 'no-store'});
            if (!response.ok) throw new Error('HTTP ' + response.status);
            const data = await response.json();
            document.getElementById('provider-live-updated').textContent = 'Updated ' + new Date(data.generated_at).toLocaleTimeString();
            document.getElementById('provider-live-panels').innerHTML = (data.providers || []).map(provider => `<div class="card"><div class="row"><strong>${provider.name}</strong><span class="tag">${provider.status}</span></div><div class="sub">${provider.host}:${provider.port} · ${provider.latency_ms} ms${provider.error ? ' · ' + provider.error : ''}</div><pre>${(provider.logs || []).map(log => `${log.created_at} | ${log.message_id} | ${log.destination} | ${log.final_status || ''} | ${log.provider_status || ''}`).join('\\n') || 'No provider SMS logs.'}</pre></div>`).join('') || '<div class="sub">No providers configured.</div>';
        } catch (error) {
            document.getElementById('provider-live-updated').textContent = 'Offline';
        }
    };
    refreshProviders();
    setInterval(refreshProviders, 5000);
</script>
@endsection
