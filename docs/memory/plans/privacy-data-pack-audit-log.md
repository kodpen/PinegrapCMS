---
name: privacy-data-pack-audit-log
description: Plan (approved 2026-10-19; steps 1-4b done, last #1706 and #1792; next 5): per-person export, erasure wizard keeping invoices, consent log, admin audit trail with before/after values
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
1. Done (#738): `consent_log`, `audit_log` (11.0.16), `includes/fn/privacy.php`, `includes/fn/audit.php`; consent writers in `core/consent-log-writers.md`.
2. Done (#813, #905, #1383): save paths in `core/audit-trail-writers.md`, the Workspace's in `core/audit-trail-workspace.md`.
3. Done (#938): `privacy_export.php`, JSON pack, PDF summary; `core/personal-data-export.md`.
4. Done (#1706): `privacy_erase.php`, `core/personal-data-erasure.md`; the owner's answers in `workflow/decisions-made-product.md`.
4b. Done (#1792): feed tokens in clear in early SEO settings lines masked in place, step 11.0.38 (`core/audit-trail-stored-values.md`).
5. Audit screen (JSON text is stored as object, list or compacted text: `core/audit-trail-stored-values.md`), export, API; retention jobs (S–M).

## Open questions?
- The export leaves out the site log, `audit_log` rows about the person and `visitors` (`core/personal-data-export.md`): add them later?
- Which step writes cookie and `contract` consent rows? `sms-and-iys.md` step 4 (PR #1581) added the e-mail marketing writers (`core/consent-log-writers.md`); the cookie notice's server endpoint and a `contract` row next to `order_documents` are not assigned.

## Where to start?
- Step 5. Read `core/audit-trail-stored-values.md` and `core/audit-trail-writers.md` first. The plan's `[NEW]` line is in (export); steps 4 and 5 widen it, no second line.
