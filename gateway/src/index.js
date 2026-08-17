import express from 'express';
import pino from 'pino';
import config from './config.js';
import { startClientServer } from './client-server.js';
import { ProviderManager } from './provider-manager.js';
import { submitWithFailover } from './router.js';
import { createRedisSubscriber, publishEvent, updateMessage } from './store.js';

const logger = pino({ level: process.env.LOG_LEVEL || 'info' });
const app = express();
const providers = new ProviderManager();
app.use(express.json({ limit: '1mb' }));
app.use('/api/v1', (req, res, next) => {
  if (!config.SMPP_GATEWAY_TOKEN || req.get('authorization') === `Bearer ${config.SMPP_GATEWAY_TOKEN}`) return next();
  return res.status(401).json({ error: 'Unauthorized gateway API request' });
});

app.get('/health', async (_req, res) => res.json({ status: 'ok', service: 'native-smpp-gateway', providers: [...providers.connections.values()].map(({ provider, state }) => ({ id: provider.id, name: provider.name, state })) }));
app.get('/ready', (_req, res) => res.json({ ready: [...providers.connections.values()].some((entry) => entry.state === 'BOUND') }));
app.get('/live/events', async (req, res) => {
  res.setHeader('Content-Type', 'text/event-stream'); res.setHeader('Cache-Control', 'no-cache'); res.setHeader('Connection', 'keep-alive'); res.flushHeaders?.();
  const channel = `gateway-${Date.now()}-${Math.random()}`;
  const subscriber = createRedisSubscriber();
  const onMessage = (_channel, message) => res.write(`data: ${message}\n\n`);
  await subscriber.subscribe('reve:gateway:events');
  subscriber.on('message', onMessage);
  req.on('close', async () => { subscriber.off('message', onMessage); await subscriber.unsubscribe('reve:gateway:events'); await subscriber.quit(); res.end(); });
  res.write(`event: ready\ndata: ${JSON.stringify({ channel })}\n\n`);
});
app.post('/api/v1/messages', async (req, res) => {
  try {
    const { message } = req.body;
    if (!message?.message_id) return res.status(422).json({ error: 'message.message_id is required' });
    const result = await submitWithFailover(providers, message);
    await updateMessage(message.message_id, { provider_id: result.provider.id, provider_submitted_at: new Date(), submitted_at: new Date(), final_status: 'SUBMITTED', provider_status: 'SUBMITTED', metadata: JSON.stringify({ ...(message.metadata || {}), provider_message_id: result.providerMessageId, route_failures: result.failures }) });
    await publishEvent('message.submitted', { message_id: message.message_id, provider_id: result.provider.id, provider_message_id: result.providerMessageId });
    return res.status(202).json({ accepted: true, message_id: message.message_id, provider_message_id: result.providerMessageId, provider_id: result.provider.id, failover_attempts: result.failures.length });
  } catch (error) {
    if (req.body?.message?.message_id) await updateMessage(req.body.message.message_id, { final_status: 'FAILED', provider_status: 'NO_PROVIDER', metadata: JSON.stringify({ error: error.message, failures: error.failures || [] }) });
    await publishEvent('message.failed', { message_id: req.body?.message?.message_id, error: error.message, failures: error.failures || [] });
    return res.status(503).json({ accepted: false, error: error.message, failures: error.failures || [] });
  }
});

startClientServer({ submit: async ({ message }) => submitWithFailover(providers, message) });
providers.start().catch((error) => logger.error(error, 'provider startup failed'));
setInterval(() => providers.refresh().catch((error) => logger.error(error, 'provider refresh failed')), 15000).unref();
app.listen(config.GATEWAY_HTTP_PORT, '0.0.0.0', () => logger.info({ port: config.GATEWAY_HTTP_PORT, smpp_port: config.SMPP_CLIENT_BIND_PORT }, 'native SMPP gateway started'));
