import React from 'react';
import { createRoot } from 'react-dom/client';
import LiveTrafficMonitor from './components/LiveTrafficMonitor';

const mount = document.getElementById('live-traffic-react');
if (mount) {
    createRoot(mount).render(<LiveTrafficMonitor eventsUrl={mount.dataset.eventsUrl || '/live/events'} />);
}
