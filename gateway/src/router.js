import { getRoute, redis } from './store.js';

export async function selectProviders(destination, source) {
  const cacheKey = `reve:route:${destination.slice(0, 8)}:${source || ''}`;
  const cached = await redis.get(cacheKey);
  if (cached) return JSON.parse(cached);
  const providers = await getRoute(destination, source);
  const ranked = providers.sort((a, b) => Number(a.priority || 100) - Number(b.priority || 100));
  await redis.set(cacheKey, JSON.stringify(ranked), 'EX', 15);
  return ranked;
}

export async function submitWithFailover(providerManager, message) {
  const providers = await selectProviders(message.destination, message.source);
  const failures = [];
  for (const provider of providers) {
    try {
      const providerMessageId = await providerManager.submit(provider, message);
      return { provider, providerMessageId, failures };
    } catch (error) {
      failures.push({ provider_id: provider.id, provider: provider.name, error: error.message });
    }
  }
  const error = new Error('No healthy provider accepted the message');
  error.failures = failures;
  throw error;
}
