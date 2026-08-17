# REVESMS-to-Laravel 12 Gap Map

## Current implementation

The current Laravel panel has working views and routes for:

| Existing area | Current coverage |
|---|---|
| Dashboard | Health, traffic, low-balance alerts |
| Users | Customer/reseller CRUD, SMPP provisioning, bind status, password/TPS/max-bind edit |
| Providers | CRUD, health checks, logs, buy billing fields |
| Rates | Buy/Sale tariffs, currency, prefix, CSV/XLSX import |
| Routing | Routing rules CRUD |
| Messages | SMS/CDR listing |
| Reports | Date, currency, client/provider filters, P&L, provider performance, CSV exports |
| Monitoring | Jasmin status, live traffic, provider health, bind metrics |
| Currencies | Currency and exchange-rate CRUD |
| Billing | Submission/DLR, prepaid/due, ledger and reconciliation foundations |

## REVESMS modules not yet represented as dedicated workflow pages

| Manual module | Proposed Laravel destination | Priority |
|---|---|---:|
| Client search and client hierarchy | `/admin/clients` | High |
| SMPP Profile | `/admin/smpp-profiles` | High |
| HTTP Profile/API access | `/admin/http-profiles` | Medium |
| SMS Contact and upload | `/admin/contacts` | Medium |
| SenderID Translation | `/admin/senderid-translations` | Medium |
| Text Translation | `/admin/text-translations` | Medium |
| Content Whitelisting | `/admin/content-whitelists` | Medium |
| Text/SenderID Blocking | `/admin/blocks` | High |
| SMS Country | `/admin/countries` | Medium |
| SMS Rate Plan | `/admin/rate-plans` | High; maps to current rates |
| SMS Route | `/admin/routes` | High; maps to current routing |
| SMS Translation | `/admin/sms-translations` | Medium |
| SMS Mask | `/admin/sms-masks` | Medium |
| SMS Campaign | `/admin/campaigns` | Medium |
| Pending Requests | `/admin/campaigns/pending` | Medium |
| Invoice Generation | `/admin/invoices/create` | High |
| Invoice Log | `/admin/invoices` | High |
| Profit Summary | `/admin/reports` | Existing; enhance |
| Recharge History | `/admin/recharges` | High |
| Contact/Campaign/SMS reports | `/admin/reports/*` | High; partially existing |
| General Activity Log | `/admin/activity-log` | High |
| Pending SMS Approval | `/admin/approvals` | Medium |
| Account Managers | `/admin/account-managers` | Medium |
| Roles | `/admin/roles` | High |
| System Configuration | `/admin/settings/system` | High |
| Payment Gateway | `/admin/settings/payment` | Medium |
| System Currency | `/admin/currencies` | Existing |
| Mail Server | `/admin/settings/mail` | Medium |
| Invoice Configuration | `/admin/settings/invoice` | Medium |
| MNP Configuration | `/admin/settings/mnp` | Low |
| System Alert | `/admin/settings/alerts` | High; partially existing |
| Restricted IPs | `/admin/security/restricted-ips` | High |
| Unauthorized Access Log | `/admin/security/access-log` | High |
| Active Login | `/admin/security/active-logins` | High |
| Payment Security | `/admin/security/payment` | Medium |

## UI redesign direction

The shared layout should use a left navigation grouped under **Client**, **Contacts**, **Messaging Controls**, **Rates and Destinations**, **Route Management**, **Campaign Management**, **Finance & Reports**, **Live Monitoring**, **Users**, **Settings**, and **Security**. Existing functional screens should retain their routes while being relabeled and grouped under this navigation.

The first implementation slice should be the shared navigation and dashboard shell, followed by high-priority missing workflows: client hierarchy, invoice/recharge, activity/security logs, and messaging controls. Low-priority campaign/MNP features can follow after the core SMPP billing and routing workflows are stable.
