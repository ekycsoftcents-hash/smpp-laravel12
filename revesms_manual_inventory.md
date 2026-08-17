# REVESMS Manual Interface Inventory

Source: `/home/ubuntu/upload/REVESMSManual2023.pdf`

## Initial findings from pages 1-5

The manual is an **Admin User Guide** for REVE SMS and presents a broad back-office workflow that goes beyond basic SMS sending. The first pages mainly contain the table of contents, which is enough to inventory the main menu structure and operational modules expected in the interface redesign.

## Top-level workflow and menu groups observed

| Group | Submodules observed in the manual |
|---|---|
| Client | SMS Client, SMPP Profile, HTTP Profile |
| Contact | SMS Contact, Upload SMS Contact |
| Text and SenderID Management | SenderID Translation, Text Translation, Content Whitelisting, Text and SenderID Blocking |
| Rates and Destinations | SMS Country, SMS Rate Plan |
| Route Management | SMS Route, SMS Translation |
| Campaign Management | SMS Mask, SMS Campaign, Pending Requests |
| Finance & Accounts Report | Invoice Generation, Invoice Log, Profit Summary, Recharge History |
| General Report | Contact Report, Campaign Report, SMS History, General Activity Log |
| Live Report | Pending SMS Approval |
| Users | Manage Users, Manage Account Manager, Manage Roles |
| Settings | System Configuration, Payment Gateway, System Currency, Mail Server Information, Invoice Configuration, MNP Configuration, System Alert |
| Security | Restricted IPs, Unauthorized Access Log, Active Login, Payment Security |

## Important workflow implications for the Laravel redesign

1. The current panel already covers parts of **clients, providers, rates, routing, finance, reports, monitoring, currencies, and users**, but the REVE SMS workflow is broader and more menu-driven.
2. The redesign should likely introduce a **left-side grouped navigation** instead of only simple page cards.
3. The current SMPP/Jasmin platform should be mapped to REVE concepts as follows:
   - SMS Client / SMPP Profile / HTTP Profile → customer, SMPP account, API access
   - SMS Rate Plan / Country → tariff management, prefix/country tables
   - SMS Route / Translation → routing rules and transformations
   - Finance & Accounts Report → billing, invoice, recharge and profit reports
   - Live Report → monitoring, approvals, bind status, traffic status
   - Settings / Security → alerts, currency, mail, IP restrictions, login history
4. Several manual modules are not yet implemented and may need to be added in the redesign scope, including:
   - contacts and contact upload
   - sender ID/text translation and blocking
   - campaign and mask management
   - invoice and recharge workflow
   - roles/account manager screens
   - restricted IP and access-log screens

## Immediate redesign direction

The interface redesign should use the manual as a **full navigation and workflow reference**, not only as a visual theme reference. The next review steps should inspect the later pages to capture actual screen layouts, forms, filters, table structures, actions, and workflow sequences for each major module.
