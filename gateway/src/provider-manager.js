import smpp from 'smpp';
import config from './config.js';
import { getProviders, publishEvent, setBindState, updateMessage } from './store.js';
import { decryptLaravel } from './laravel-crypto.js';

function providerPassword(provider) {
  return decryptLaravel(provider.password);
}

export class ProviderManager {
  constructor() {
    this.connections = new Map();
    this.backoff = new Map();
  }

  async start() {
    const providers = await getProviders();
    await Promise.all(providers.map((provider) => this.connect(provider)));
  }

  async refresh() {
    const providers = await getProviders();
    const active = new Set(providers.map((provider) => String(provider.id)));
    for (const [id, item] of this.connections) {
      if (!active.has(id)) { item.session.close(); this.connections.delete(id); }
    }
    await Promise.all(providers.map((provider) => {
      const current = this.connections.get(String(provider.id));
      return current?.state === 'BOUND' ? undefined : this.connect(provider);
    }));
  }

  async connect(provider) {
    const key = String(provider.id);
    if (this.connections.get(key)?.state === 'CONNECTING' || this.connections.get(key)?.state === 'BOUND') return;
    const item = { provider, state: 'CONNECTING', session: null, connectedAt: null, lastError: null };
    this.connections.set(key, item);
    const session = smpp.connect({ host: provider.host, port: Number(provider.port || 2775), connectTimeout: config.PROVIDER_CONNECT_TIMEOUT_MS });
    item.session = session;
    session.on('connect', () => {
      session.bind_transceiver({ system_id: provider.system_id, password: providerPassword(provider), system_type: config.SMPP_SYSTEM_TYPE }, (pdu) => {
        if (pdu.command_status !== 0) return this.markDown(item, new Error(`bind failed: ${pdu.command_status}`));
        item.state = 'BOUND'; item.connectedAt = new Date(); this.backoff.delete(key);
        setBindState(`provider:${provider.id}`, { provider_id: provider.id, name: provider.name, state: 'BOUND', bound_at: item.connectedAt.toISOString() });
        publishEvent('provider.bind', { provider_id: provider.id, name: provider.name });
      });
    });
    session.on('deliver_sm', async (pdu) => {
      const dlr = pdu.short_message?.message ?? pdu.short_message ?? '';
      const providerMessageId = pdu.receipted_message_id || dlr.match(/id:([^ ]+)/)?.[1];
      const status = pdu.message_state || dlr.match(/stat:([^ ]+)/)?.[1] || 'UNKNOWN';
      if (providerMessageId) await updateMessageByProviderId(providerMessageId, String(status).toUpperCase(), provider.id);
      session.send(pdu.response());
    });
    session.on('enquire_link', (pdu) => session.send(pdu.response()));
    session.on('close', () => this.markDown(item, new Error('provider connection closed')));
    session.on('error', (error) => this.markDown(item, error));
  }

  async markDown(item, error) {
    if (item.state === 'DOWN') return;
    item.state = 'DOWN'; item.lastError = error.message;
    await setBindState(`provider:${item.provider.id}`, { provider_id: item.provider.id, name: item.provider.name, state: 'DOWN', error: error.message, at: new Date().toISOString() }, 180);
    await publishEvent('provider.down', { provider_id: item.provider.id, name: item.provider.name, error: error.message });
    const key = String(item.provider.id);
    const current = this.backoff.get(key) || config.SMPP_RECONNECT_MIN_MS;
    this.backoff.set(key, Math.min(current * 2, config.SMPP_RECONNECT_MAX_MS));
    setTimeout(() => this.connect(item.provider), current).unref();
  }

  async submit(provider, message) {
    const item = this.connections.get(String(provider.id));
    if (!item || item.state !== 'BOUND') throw new Error(`Provider ${provider.name || provider.id} is not bound`);
    return await new Promise((resolve, reject) => {
      const started = Date.now();
      item.session.submit_sm({
        source_addr: message.source,
        destination_addr: message.destination,
        short_message: message.message,
        registered_delivery: 1,
        data_coding: /[^\x00-\x7F]/.test(message.message) ? 8 : 0
      }, (pdu) => {
        if (pdu.command_status !== 0) return reject(new Error(`submit_sm failed: ${pdu.command_status}`));
        publishEvent('provider.submit', { provider_id: provider.id, message_id: message.message_id, provider_message_id: pdu.message_id, latency_ms: Date.now() - started });
        resolve(pdu.message_id);
      });
    });
  }
}

async function updateMessageByProviderId(providerMessageId, status, providerId) {
  const mapped = ['DELIVRD', 'DELIVERED'].includes(status) ? 'DELIVERED' : ['UNDELIV', 'REJECTD', 'FAILED'].includes(status) ? 'FAILED' : status === 'EXPIRED' ? 'EXPIRED' : 'UNKNOWN';
  const { findMessageByProviderId } = await import('./store.js');
  const message = await findMessageByProviderId(providerMessageId);
  if (!message) return;
  const metadata = { ...(message.metadata || {}), provider_status: status, provider_id: providerId };
  await updateMessage(message.message_id, { final_status: mapped, customer_status: mapped, provider_status: status, dlr_at: new Date(), metadata: JSON.stringify(metadata) });
  await publishEvent('message.dlr', { message_id: message.message_id, provider_message_id: providerMessageId, status: mapped, provider_status: status });
}
