@props(['eventsUrl' => null])
<div x-data="liveTraffic('{{ $eventsUrl ?: config('smpp.gateway.events_url') }}')" x-init="start()" class="card" style="margin-top:15px">
    <div class="top" style="margin-bottom:14px"><div><h2 style="margin:0">Live traffic cockpit</h2><div class="sub">Native gateway events stream without page refresh.</div></div><span class="pill" x-text="connected ? '● SSE CONNECTED' : '● RECONNECTING'"></span></div>
    <div class="grid" style="grid-template-columns:repeat(5,minmax(0,1fr))">
        <div><div class="label">Submitted</div><div class="value good" x-text="metrics.submitted"></div></div>
        <div><div class="label">Delivered</div><div class="value" x-text="metrics.delivered"></div></div>
        <div><div class="label">Failed</div><div class="value bad" x-text="metrics.failed"></div></div>
        <div><div class="label">Provider binds</div><div class="value" x-text="metrics.providerBinds"></div></div>
        <div><div class="label">Client binds</div><div class="value" x-text="metrics.clientBinds"></div></div>
    </div>
    <div class="row" style="margin-top:12px"><span>Last event: <strong x-text="lastEvent"></strong></span><span class="sub" x-text="lastAt"></span></div>
    <div class="tablewrap" style="margin-top:12px"><table><thead><tr><th>Time</th><th>Event</th><th>Message / Provider</th><th>Status</th><th>Latency</th></tr></thead><tbody><template x-for="item in events" :key="item.id"><tr><td x-text="item.time"></td><td><span class="tag" x-text="item.event"></span></td><td x-text="item.subject"></td><td x-text="item.status"></td><td x-text="item.latency"></td></tr></template><tr x-show="events.length === 0"><td colspan="5">Waiting for gateway events…</td></tr></tbody></table></div>
</div>
<script>
function liveTraffic(url) {
    return { source:null, connected:false, lastEvent:'waiting', lastAt:'—', events:[], metrics:{submitted:0,delivered:0,failed:0,providerBinds:0,clientBinds:0}, start(){ this.connect(); }, connect(){ this.source = new EventSource(url); this.source.onopen = () => this.connected = true; this.source.onerror = () => { this.connected = false; this.source.close(); setTimeout(() => this.connect(), 3000); }; this.source.onmessage = (event) => this.consume(event.data); }, consume(raw){ try { const packet = JSON.parse(raw); const event = packet.event || 'gateway.event'; const payload = packet.payload || {}; this.lastEvent = event; this.lastAt = new Date(packet.emitted_at || Date.now()).toLocaleTimeString(); if(event === 'message.submitted') this.metrics.submitted++; if(event === 'message.dlr' && payload.status === 'DELIVERED') this.metrics.delivered++; if(event === 'message.failed' || (event === 'message.dlr' && ['FAILED','EXPIRED'].includes(payload.status))) this.metrics.failed++; if(event === 'provider.bind') this.metrics.providerBinds++; if(event === 'client.bind') this.metrics.clientBinds++; this.events.unshift({id:Date.now()+Math.random(),time:this.lastAt,event,subject:payload.message_id || payload.name || payload.system_id || '—',status:payload.status || payload.state || '—',latency:payload.latency_ms ? payload.latency_ms+' ms' : '—'}); this.events = this.events.slice(0,20); } catch(e) {} } }
}
</script>
