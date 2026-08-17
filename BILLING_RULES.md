# Dual-side billing rules

The billing engine supports independent modes for the customer side and provider side. Modes are stored as `customer_billing_mode` and `provider_billing_mode` on users, and `billing_mode` on providers. Valid values are `SUBMISSION` and `DLR`.

| Side | Mode | Charge/cost event | Failed/expired behavior |
|---|---|---|---|
| Customer | SUBMISSION | Debit customer after the native gateway accepts a provider submission | Credit a full refund on FAILED, REJECTED, or EXPIRED DLR |
| Customer | DLR | Debit customer only when DLR is DELIVERED | No debit for failed, expired, or unknown |
| Provider | SUBMISSION | Post provider cost after successful native gateway submission | Post provider refund on FAILED, REJECTED, or EXPIRED DLR |
| Provider | DLR | Post provider cost only when DLR is DELIVERED | No cost for failed, expired, or unknown |

Each posting is protected by a unique billing event key, so a retried queue job or duplicate provider DLR cannot double-charge the same message. `customer_charge`, `provider_cost`, and `profit` are recalculated from posted billing events on every billing transition.

The implementation lives in `app/Services/Billing/BillingService.php`. The `SendSmsToGateway` queue job invokes `onSubmission()` after the Node.js gateway returns a provider message ID. The Node.js provider manager updates the shared `sms_messages` row when it receives `deliver_sm`, and the reconciliation flow invokes `onDlr()` after mapping the receipt status. Rates are snapshotted on `sms_messages` through `sell_rate`, `buy_rate`, and `segments`.

For production use, protect rate and account selection behind authenticated API credentials, perform provider routing before dispatching the job, and keep invoice payments separate from SMS traffic ledger entries. The native gateway internal API is protected with `SMPP_GATEWAY_TOKEN`.
