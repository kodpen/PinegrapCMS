---
name: privacy-data-pack-audit-log
description: Plan (approved 2026-10-19; steps 1-3 done: #738, #813, #905, #1383, #938; next step 4): per-person export, erasure wizard keeping invoices, consent log, admin audit trail with before/after values
---

## Why?
- KVKK and GDPR requests cannot be served from the panel: no per-person export, no erasure tool (only visitor IPs are removed automatically), consent lives only in the `pg_consent` cookie, and the site log (`log_activity()`, `view_log.php`) is free text kept six months without entity ids or before/after values. ERP has a structured trail (`erp_audit_log`); the rest of the panel does not.

## What is decided?
- Export: `privacy_export.php` (manager+), JSON plus a PDF summary, logged in the audit trail; built in step 3 (`core/personal-data-export.md`).
- Erasure: a wizard that shows what will be deleted and what will be anonymised; orders and ERP invoices are kept for the legal retention period with the personal fields replaced (`name → "Deleted customer"`, e-mail → hashed token, addresses cleared), comments reattributed to "Anonymous", sessions and MFA removed, contact rows deleted, consents kept as proof with the person token only. One helper per table in `includes/fn/privacy.php`; dry-run first, then apply; a test pins the field list.
- Consent log: table `consent_log` (person token, user_id/contact_id, channel `cookie|email|sms|call|contract`, purpose, status, source, ip, user_agent, created_at), written by the cookie banner (server endpoint), sign-up, checkout, e-mail preferences, İYS sync (`sms-and-iys.md`) and the legal documents (`checkout-legal-documents.md`). Retention 3 years, trimmed by the general job.
- Audit trail: table `audit_log` (user_id, action, entity type, entity id, before_json, after_json, ip, request_id, created_at) written by a helper `pg_audit()` from the save paths of settings, users, products, pages, orders, files, offers and API keys; the free-text `log_activity()` stays and keeps feeding the Activity card (no removal). Audit screen with filters and CSV export; retention setting (default 2 years). API `GET /audit-log` scope `system:read`.
- Retention settings for contacts (inactive N years → anonymise), form submissions and orders (anonymise after N years) are opt-in and off by default.

## What remains?
1. Done (#738): `consent_log`, `audit_log` (11.0.16), `includes/fn/privacy.php`, `includes/fn/audit.php`; no consent writer yet.
2. Done (#813, #905, #1383): save paths in `core/audit-trail-writers.md`, the Workspace's in `core/audit-trail-workspace.md`.
3. Done (#938): `privacy_export.php`, JSON pack, PDF summary; `core/personal-data-export.md`.
4. Erasure wizard with dry-run (M).
5. Audit screen (JSON text is stored as object, list or compacted text: `core/audit-trail-stored-values.md`), export, API; retention jobs (S–M).

## Open questions?
- Owner: a site that ran `development` between #813 and #905 may keep the feed token in clear text in `audit_log` rows with `label = 'seo'`. Delete them?
- The export leaves out the site log, `audit_log` rows about the person and `visitors` (`core/personal-data-export.md`): add them later?
- A deleted order or user keeps its whole row in `audit_log.before_json`: anonymise it in step 4 or keep it for the trail's retention?
- Keep order e-mail for refund disputes after erasure? Keep the hashed token and the PSP reference only; refunds use the PSP reference.
- Which step hooks the consent writers? None of the steps above names them. `sms-and-iys.md` step 4 covers sign-up, account, checkout and unsubscribe for e-mail and SMS; the cookie notice's server endpoint and a `contract` row next to `order_documents` are not assigned.

## Where to start?
- Step 4. Erasure reuses `pg_privacy_export_find()`; `core/personal-data-export.md` lists where a person's data lives. Read `core/consent-log-and-audit-trail.md` and `core/audit-trail-writers.md` first. The plan's `[NEW]` line is in (export); step 4 widens it, no second line.
