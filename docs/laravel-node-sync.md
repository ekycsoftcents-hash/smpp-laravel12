# Laravel and Native Node.js Gateway Synchronization

The platform uses one PostgreSQL database as the source of truth. Laravel owns users, customer SMPP accounts, providers, routing rules, rates, invoices, ledger entries, and permanent SMS records. Node.js owns live SMPP sessions, provider connections, routing execution, submission, DLR reception, retries, and gateway events.

> Laravel should not duplicate the gateway's live connection state in a second database. It should read permanent records from PostgreSQL and live state from the gateway health/SSE APIs.

## Submission flow

1. Laravel validates the client request, resolves the best current rate, creates an `sms_messages` row with a UUID and idempotency key, and dispatches `SendSmsToGateway`.
2. The queued job calls `NativeSmppGatewayClient::submit()` with the database message UUID and the message payload.
3. Node.js authenticates the bearer token, selects a provider using country/prefix/sender rules, sends `submit_sm`, and receives the provider message ID.
4. Node.js updates the same `sms_messages` row with `provider_id`, `provider_submitted_at`, `provider_status`, and provider message metadata.
5. Laravel's job records submission billing through `BillingService`. The unique billing event key prevents duplicate debit during retries.

The Laravel request looks like this:

```php
$result = $gateway->submit([
    'message_id' => $message->message_id,
    'customer_id' => $message->customer_id,
    'source' => $message->source,
    'destination' => $message->destination,
    'message' => $message->message,
    'segments' => $message->segments,
    'metadata' => json_decode($message->metadata ?: '{}', true) ?: [],
]);
```

The corresponding authenticated HTTP request is:

```http
POST /api/v1/messages
Authorization: Bearer ${SMPP_GATEWAY_TOKEN}
Content-Type: application/json

{"message":{"message_id":"uuid","customer_id":12,"source":"BRAND","destination":"8801...","message":"Hello","segments":1}}
```

A successful response is `202 Accepted`:

```json
{
  "accepted": true,
  "message_id": "uuid",
  "provider_message_id": "abc123",
  "provider_id": 4,
  "failover_attempts": 0
}
```

A provider failure is recorded against the same message UUID. The gateway tries the next ranked provider before returning `503`. Laravel's queue retry policy then retries only while the message is not terminal.

## Database synchronization contract

| Table | Laravel writes | Node.js writes |
|---|---|---|
| `users` | Balance, account, currency | Never directly |
| `customer_smpp_accounts` | Credentials, enable flag, bind/TPS policy | Reads credentials and policy |
| `providers` | Credentials, route configuration | Provider health fields and status may be updated by health worker |
| `routing_rules` | Country, prefix, operator, priority, strategy | Reads and caches for route selection |
| `rates` | Buy/sale tariffs and validity | Reads for Laravel-created message pricing |
| `sms_messages` | Initial message and billing fields | Provider ID, provider ID, submit state, DLR state, metadata |
| `billing_events` | Billing service only | Never directly |
| `invoices` / `invoice_payments` | Invoice and finance workflows | Never directly |

All Node.js updates use the message UUID or provider message ID correlation stored in `sms_messages.metadata`. DLR updates are idempotent because the same delivery event maps to the same message row and the Laravel billing layer checks its unique event key before posting a ledger entry.

## DLR flow

The provider sends `deliver_sm` to Node.js. Node.js extracts `receipted_message_id` or the provider receipt ID, maps provider statuses such as `DELIVRD`, `UNDELIV`, `EXPIRED`, and `REJECTD`, updates `sms_messages`, and emits `message.dlr` to Redis Pub/Sub. The Laravel dashboard sees the event through SSE, while the permanent message state remains in PostgreSQL.

```js
const message = await findMessageByProviderId(providerMessageId);
await updateMessage(message.message_id, {
  final_status: mappedStatus,
  customer_status: mappedStatus,
  provider_status: providerStatus,
  dlr_at: new Date(),
  metadata: JSON.stringify(metadata),
});
await publishEvent('message.dlr', {
  message_id: message.message_id,
  status: mappedStatus,
});
```

## Live events

Node.js publishes JSON packets to Redis channel `reve:gateway:events`. Each browser receives an independent subscription at `/live/events`.

```json
{
  "event": "message.dlr",
  "payload": {
    "message_id": "uuid",
    "status": "DELIVERED",
    "provider_message_id": "abc123"
  },
  "emitted_at": "2026-08-17T12:00:00.000Z"
}
```

The React component in `resources/js/components/LiveTrafficMonitor.jsx` consumes this event stream with `EventSource`, reconnects after a connection failure, and maintains a bounded recent-events list so a long-running admin tab does not grow without limit.

## Required environment contract

```env
APP_KEY=base64:the-same-key-used-by-laravel
SMPP_GATEWAY_TOKEN=use-a-long-random-secret
SMPP_GATEWAY_URL=http://gateway:3001
SMPP_GATEWAY_EVENTS_URL=http://gateway:3001/live/events
DATABASE_URL=postgres://smpp:smpp_secret@postgres:5432/smpp
REDIS_URL=redis://redis:6379
```

Do not expose `SMPP_GATEWAY_TOKEN`, `APP_KEY`, or PostgreSQL credentials to browser JavaScript. The React component should receive only the SSE URL; the browser does not need the internal submission token.
