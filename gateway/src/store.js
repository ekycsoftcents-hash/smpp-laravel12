import pg from 'pg';
import Redis from 'ioredis';
import config from './config.js';

const { Pool } = pg;
export const pool = new Pool({ connectionString: config.DATABASE_URL, max: 20, idleTimeoutMillis: 30000 });
export const redis = new Redis(config.REDIS_URL, { maxRetriesPerRequest: null, lazyConnect: false });
export function createRedisSubscriber() { return new Redis(config.REDIS_URL, { maxRetriesPerRequest: null }); }

export async function getClientAccount(systemId) {
  const { rows } = await pool.query(`
    SELECT a.*, u.id AS customer_id, u.balance, u.currency
    FROM customer_smpp_accounts a
    JOIN users u ON u.id = a.user_id
    WHERE a.system_id = $1 AND a.enabled = true
    LIMIT 1`, [systemId]);
  return rows[0] ?? null;
}

export async function getProviders() {
  const { rows } = await pool.query(`
    SELECT p.*, r.priority, r.strategy, r.country, r.operator, r.prefix
    FROM providers p
    LEFT JOIN routing_rules r ON r.provider_id = p.id AND r.enabled = true
    WHERE COALESCE(p.enabled, true) = true
    ORDER BY COALESCE(r.priority, 100), p.id`);
  return rows;
}

export async function getRoute(destination, source) {
  const country = destination.replace(/^00/, '+').match(/^\+(\d{1,3})/)?.[1] ?? null;
  const providers = await getProviders();
  const matching = providers.filter((provider) => {
    const countryMatch = !provider.country || !country || provider.country === country;
    const prefixMatch = !provider.prefix || destination.startsWith(String(provider.prefix));
    const senderMatch = !provider.sender_id || provider.sender_id === source;
    return countryMatch && prefixMatch && senderMatch;
  });
  return matching.length ? matching : providers;
}

export async function saveMessage({ messageId, customerId, source, destination, text, segments, currency, metadata }) {
  const { rows } = await pool.query(`
    INSERT INTO sms_messages
      (message_id, customer_id, source, destination, message, segments, final_status, customer_status, currency, metadata, created_at, updated_at)
    VALUES ($1, $2, $3, $4, $5, $6, 'ACCEPTED', 'ACCEPTED', $7, $8, NOW(), NOW())
    ON CONFLICT (message_id) DO UPDATE SET updated_at = NOW()
    RETURNING *`, [messageId, customerId, source, destination, text, segments, currency, metadata]);
  return rows[0];
}

export async function updateMessage(messageId, patch) {
  const entries = Object.entries(patch);
  if (!entries.length) return;
  const values = [messageId];
  const sets = entries.map(([key, value], index) => {
    values.push(value);
    return `${key} = $${index + 2}`;
  });
  values.push(new Date());
  await pool.query(`UPDATE sms_messages SET ${sets.join(', ')}, updated_at = $${values.length} WHERE message_id = $1`, values);
}

export async function findMessageByProviderId(providerMessageId) {
  const { rows } = await pool.query(`
    SELECT * FROM sms_messages
    WHERE metadata->>'provider_message_id' = $1
       OR metadata->>'smpp_message_id' = $1
    ORDER BY id DESC LIMIT 1`, [providerMessageId]);
  return rows[0] ?? null;
}

export async function publishEvent(event, payload) {
  const message = JSON.stringify({ event, payload, emitted_at: new Date().toISOString() });
  await redis.publish('reve:gateway:events', message);
  await redis.set(`reve:live:${event}`, message, 'EX', 120);
}

export async function setBindState(key, state, ttl = 180) {
  await redis.set(`reve:bind:${key}`, JSON.stringify(state), 'EX', ttl);
}
