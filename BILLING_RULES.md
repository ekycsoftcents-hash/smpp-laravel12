# Dual-side billing rules

The billing engine supports independent modes for the customer side and provider side. Modes are stored as `customer_billing_mode` and `provider_billing_mode` on users, and `billing_mode` on providers. Valid values are `SUBMISSION` and `DLR`.

| Side | Mode | Charge/cost event | Failed/expired behavior |
|---|---|---|---|
| Customer | SUBMISSION | Debit customer at successful Jasmin submission | Credit a full refund on FAILED, REJECTED or EXPIRED DLR |
| Customer | DLR | Debit customer only when DLR is DELIVERED | No debit for failed/expired/unknown |
| Provider | SUBMISSION | Post provider cost at successful Jasmin submission | Post provider refund on FAILED, REJECTED or EXPIRED DLR |
| Provider | DLR | Post provider cost only when DLR is DELIVERED | No cost for failed/expired/unknown |

Each posting is protected by a unique billing event key, so a retried queue job or duplicate DLR callback cannot double-charge the same message. `customer_charge`, `provider_cost`, and `profit` are recalculated from posted billing events on every billing transition.

The implementation lives in `app/Services/Billing/BillingService.php`. The submission queue job invokes `onSubmission()` after Jasmin returns a provider message ID. The Jasmin callback invokes `onDlr()` after mapping the receipt status. Rates are snapshotted on `sms_messages` through `sell_rate`, `buy_rate`, and `segments`.

For production use, protect rate and account selection behind authenticated API credentials and perform provider routing before dispatching the job; the current API accepts optional `customer_id`, `provider_id`, `sell_rate`, and `buy_rate` values to make the integration path testable locally.
