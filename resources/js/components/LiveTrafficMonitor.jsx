import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

const initialMetrics = { submitted: 0, delivered: 0, failed: 0, providerBinds: 0, clientBinds: 0 };

function normalizeEvent(packet) {
    const payload = packet?.payload ?? {};
    return {
        id: `${Date.now()}-${Math.random()}`,
        time: new Date(packet?.emitted_at ?? Date.now()).toLocaleTimeString(),
        event: packet?.event ?? 'gateway.event',
        subject: payload.message_id ?? payload.name ?? payload.system_id ?? '—',
        status: payload.status ?? payload.state ?? '—',
        latency: payload.latency_ms ? `${payload.latency_ms} ms` : '—',
    };
}

export default function LiveTrafficMonitor({ eventsUrl = '/live/events', className = '' }) {
    const sourceRef = useRef(null);
    const reconnectTimerRef = useRef(null);
    const [connected, setConnected] = useState(false);
    const [metrics, setMetrics] = useState(initialMetrics);
    const [events, setEvents] = useState([]);
    const [lastEvent, setLastEvent] = useState('waiting');
    const [lastAt, setLastAt] = useState('—');

    const consume = useCallback((raw) => {
        try {
            const packet = JSON.parse(raw);
            const event = normalizeEvent(packet);
            const type = packet?.event;
            const status = packet?.payload?.status;
            setLastEvent(type ?? 'gateway.event');
            setLastAt(event.time);
            setMetrics((current) => ({
                ...current,
                submitted: current.submitted + (type === 'message.submitted' ? 1 : 0),
                delivered: current.delivered + (type === 'message.dlr' && status === 'DELIVERED' ? 1 : 0),
                failed: current.failed + (type === 'message.failed' || (type === 'message.dlr' && ['FAILED', 'EXPIRED'].includes(status)) ? 1 : 0),
                providerBinds: current.providerBinds + (type === 'provider.bind' ? 1 : 0),
                clientBinds: current.clientBinds + (type === 'client.bind' ? 1 : 0),
            }));
            setEvents((current) => [event, ...current].slice(0, 30));
        } catch {
            // Ignore malformed events; the next valid event should keep the stream useful.
        }
    }, []);

    const connect = useCallback(() => {
        if (!eventsUrl || typeof window === 'undefined' || !('EventSource' in window)) return undefined;
        const source = new EventSource(eventsUrl);
        sourceRef.current = source;
        source.onopen = () => setConnected(true);
        source.onmessage = (event) => consume(event.data);
        source.onerror = () => {
            setConnected(false);
            source.close();
            reconnectTimerRef.current = window.setTimeout(connect, 3000);
        };
        return source;
    }, [consume, eventsUrl]);

    useEffect(() => {
        const source = connect();
        return () => {
            source?.close();
            if (reconnectTimerRef.current) window.clearTimeout(reconnectTimerRef.current);
        };
    }, [connect]);

    const cards = useMemo(() => [
        ['Submitted', metrics.submitted, 'text-emerald-600'],
        ['Delivered', metrics.delivered, 'text-sky-700'],
        ['Failed', metrics.failed, 'text-rose-600'],
        ['Provider binds', metrics.providerBinds, 'text-indigo-700'],
        ['Client binds', metrics.clientBinds, 'text-amber-600'],
    ], [metrics]);

    return (
        <section className={`rounded-xl border border-slate-200 bg-white p-5 shadow-sm ${className}`}>
            <header className="mb-5 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p className="text-xs font-bold uppercase tracking-[0.18em] text-sky-700">Operations cockpit</p>
                    <h2 className="mt-1 text-lg font-extrabold text-slate-800">Live traffic monitor</h2>
                    <p className="mt-1 text-sm text-slate-500">Native SMPP gateway events without page refresh.</p>
                </div>
                <span className={`rounded-full border px-3 py-1 text-xs font-bold ${connected ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700'}`}>
                    {connected ? '● SSE CONNECTED' : '● RECONNECTING'}
                </span>
            </header>
            <div className="grid grid-cols-2 gap-3 md:grid-cols-5">
                {cards.map(([label, value, color]) => <div key={label} className="rounded-lg bg-slate-50 p-3"><p className="text-xs text-slate-500">{label}</p><p className={`mt-2 text-2xl font-extrabold ${color}`}>{value}</p></div>)}
            </div>
            <div className="mt-4 flex flex-wrap justify-between gap-2 border-b border-slate-100 pb-3 text-sm text-slate-600"><span>Last event: <strong>{lastEvent}</strong></span><span className="text-slate-400">{lastAt}</span></div>
            <div className="mt-3 overflow-x-auto"><table className="w-full min-w-[680px] text-left text-sm"><thead><tr className="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500"><th className="px-3 py-3">Time</th><th className="px-3 py-3">Event</th><th className="px-3 py-3">Message / Provider</th><th className="px-3 py-3">Status</th><th className="px-3 py-3">Latency</th></tr></thead><tbody>{events.map((item) => <tr key={item.id} className="border-b border-slate-100"><td className="px-3 py-3 text-slate-500">{item.time}</td><td className="px-3 py-3"><span className="rounded bg-emerald-50 px-2 py-1 text-xs font-bold text-emerald-700">{item.event}</span></td><td className="px-3 py-3 font-medium text-slate-700">{item.subject}</td><td className="px-3 py-3 text-slate-600">{item.status}</td><td className="px-3 py-3 text-slate-500">{item.latency}</td></tr>)}{events.length === 0 && <tr><td colSpan="5" className="px-3 py-8 text-center text-slate-400">Waiting for gateway events…</td></tr>}</tbody></table></div>
        </section>
    );
}
