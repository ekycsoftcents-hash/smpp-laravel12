-- Read-only downtime recovery checks for PostgreSQL.
-- Run with:
-- docker compose exec -T postgres psql -U smpp -d smpp < scripts/downtime_sync.sql

\echo '1) Container-side database timestamp'
SELECT NOW() AS database_now;

\echo '2) SMS status counts'
SELECT final_status, provider_status, COUNT(*) AS message_count
FROM sms_messages
GROUP BY final_status, provider_status
ORDER BY final_status, provider_status;

\echo '3) Recent messages that are still unresolved'
SELECT id, message_id, customer_id, provider_id, final_status, provider_status,
       submitted_at, provider_submitted_at, dlr_at
FROM sms_messages
WHERE final_status IN ('UNKNOWN', 'QUEUED', 'SUBMITTED')
ORDER BY id DESC
LIMIT 100;

\echo '4) Submission billing events missing for submitted messages'
SELECT s.id, s.message_id, s.final_status, s.customer_id, s.provider_id
FROM sms_messages s
LEFT JOIN billing_events c
  ON c.sms_message_id = s.id
 AND c.event_key = lower('customer:sms_submission_charge:' || s.id)
LEFT JOIN billing_events p
  ON p.sms_message_id = s.id
 AND p.event_key = lower('provider:provider_submission_cost:' || s.id)
WHERE s.final_status IN ('SUBMITTED', 'QUEUED', 'DELIVERED', 'FAILED', 'REJECTED', 'EXPIRED')
  AND ((s.customer_id IS NOT NULL AND c.id IS NULL)
    OR (s.provider_id IS NOT NULL AND p.id IS NULL));

\echo '5) Delivered DLR billing events missing'
SELECT s.id, s.message_id, s.customer_id, s.provider_id
FROM sms_messages s
LEFT JOIN billing_events c
  ON c.sms_message_id = s.id
 AND c.event_key = lower('customer:sms_dlr_charge:' || s.id)
LEFT JOIN billing_events p
  ON p.sms_message_id = s.id
 AND p.event_key = lower('provider:provider_dlr_cost:' || s.id)
WHERE s.final_status = 'DELIVERED'
  AND ((s.customer_id IS NOT NULL AND EXISTS (
          SELECT 1 FROM users u WHERE u.id = s.customer_id AND UPPER(u.customer_billing_mode) = 'DLR'
        ) AND c.id IS NULL)
    OR (s.provider_id IS NOT NULL AND EXISTS (
          SELECT 1 FROM providers pr WHERE pr.id = s.provider_id AND UPPER(pr.billing_mode) = 'DLR'
        ) AND p.id IS NULL));

\echo '6) Failure refund events missing'
SELECT s.id, s.message_id, s.final_status, s.customer_id, s.provider_id
FROM sms_messages s
LEFT JOIN billing_events c
  ON c.sms_message_id = s.id
 AND c.event_key = lower('customer:sms_refund:' || s.id)
LEFT JOIN billing_events p
  ON p.sms_message_id = s.id
 AND p.event_key = lower('provider:provider_refund:' || s.id)
WHERE s.final_status IN ('FAILED', 'REJECTED', 'EXPIRED')
  AND ((s.customer_id IS NOT NULL AND EXISTS (
          SELECT 1 FROM users u WHERE u.id = s.customer_id AND UPPER(u.customer_billing_mode) = 'SUBMISSION'
        ) AND c.id IS NULL)
    OR (s.provider_id IS NOT NULL AND EXISTS (
          SELECT 1 FROM providers pr WHERE pr.id = s.provider_id AND UPPER(pr.billing_mode) = 'SUBMISSION'
        ) AND p.id IS NULL));

\echo '7) Duplicate billing event keys'
SELECT event_key, COUNT(*) AS duplicate_count
FROM billing_events
WHERE event_key IS NOT NULL
GROUP BY event_key
HAVING COUNT(*) > 1
ORDER BY duplicate_count DESC, event_key;

\echo '8) Billing events with no matching SMS'
SELECT b.id, b.event_key, b.sms_message_id, b.side, b.event_type, b.amount
FROM billing_events b
LEFT JOIN sms_messages s ON s.id = b.sms_message_id
WHERE s.id IS NULL
ORDER BY b.id DESC;

\echo '9) Customer balance versus last ledger balance'
SELECT u.id, u.name, u.balance AS users_balance,
       last_entry.balance_after AS last_ledger_balance,
       a.enabled AS smpp_enabled,
       a.system_id
FROM users u
LEFT JOIN customer_smpp_accounts a ON a.user_id = u.id
LEFT JOIN LATERAL (
    SELECT le.balance_after
    FROM ledger_entries le
    WHERE le.account_id = u.id AND le.side = 'CUSTOMER'
    ORDER BY le.id DESC
    LIMIT 1
) last_entry ON TRUE
WHERE u.account_type IN ('customer', 'reseller')
ORDER BY u.id;

\echo '10) Customers below the 1.00 SMPP threshold that are still locally enabled'
SELECT u.id, u.name, u.balance, a.system_id, a.enabled
FROM users u
JOIN customer_smpp_accounts a ON a.user_id = u.id
WHERE u.balance < 1.00 AND a.enabled = TRUE
ORDER BY u.id;
