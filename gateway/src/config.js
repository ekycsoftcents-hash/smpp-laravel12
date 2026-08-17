import { z } from 'zod';

const env = z.object({
  NODE_ENV: z.string().default('production'),
  APP_KEY: z.string().min(1),
  GATEWAY_HTTP_PORT: z.coerce.number().default(3001),
  SMPP_CLIENT_BIND_HOST: z.string().default('0.0.0.0'),
  SMPP_CLIENT_BIND_PORT: z.coerce.number().default(2775),
  DATABASE_URL: z.string().default('postgres://smpp:smpp_secret@postgres:5432/smpp'),
  REDIS_URL: z.string().default('redis://redis:6379'),
  SMPP_SYSTEM_TYPE: z.string().default(''),
  SMPP_ENQUIRE_LINK_INTERVAL: z.coerce.number().default(30000),
  SMPP_RECONNECT_MIN_MS: z.coerce.number().default(1000),
  SMPP_RECONNECT_MAX_MS: z.coerce.number().default(30000),
  ROUTE_CACHE_SECONDS: z.coerce.number().default(15),
  PROVIDER_CONNECT_TIMEOUT_MS: z.coerce.number().default(10000),
  SMPP_MAX_PDU_SIZE: z.coerce.number().default(65535)
}).parse(process.env);

export default env;
