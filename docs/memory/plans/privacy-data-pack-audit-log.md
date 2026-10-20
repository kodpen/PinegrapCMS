---
name: privacy-data-pack-audit-log
description: Plan (approved 2026-10-19; step 1 done, PR #738): per-person data export, erasure/anonymisation wizard respecting invoice retention, consent log, structured admin audit trail with before/after values
---

## Why?
- KVKK and GDPR requests cannot be served from the panel: no per-person export, no erasure tool (only visitor IPs are removed automatically), consent lives only in the `pg_consent` cookie, and the site log (`log_activity()`, `view_log.php`) is free text kept six months without entity ids or before/after values. ERP has a structured trail (`erp_audit_log`); the rest of the panel does not.

## What is decided?
- Export: `privacy_export.php` (manager+) collects everything tied to a person by e-mail/user id/contact id: user, contact, address book, orders and items, form submissions, comments, reviews, wishlist, consents, sessions, ERP account and documents (references only), Workspace guest data; output JSON plus a PDF summary (dompdf). Logged in the audit trail.
- Erasure: a wizard that shows what will be deleted and what will be anonymised; orders and ERP invoices are kept for the legal retention period with the personal fields replaced (`name → "Deleted customer"`, e-mail → hashed token, addresses cleared), comments reattributed to "Anonymous", sessions and MFA removed, contact rows deleted, consents kept as proof with the person token only. One helper per table in `includes/fn/privacy.php`; dry-run first, then apply; a test pins the field list.
- Consent log: table `consent_log` (person token, user_id/contact_id, channel `cookie|email|sms|call|contract`, purpose, status, source, ip, user_agent, created_at), written by the cookie banner (server endpoint), sign-up, checkout, e-mail preferences, İYS sync (`sms-and-iys.md`) and the legal documents (`checkout-legal-documents.md`). Retention 3 years, trimmed by the general job.
- Audit trail: table `audit_log` (user_id, action, entity type, entity id, before_json, after_json, ip, request_id, created_at) written by a helper `pg_audit()` from the save paths of settings, users, products, pages, orders, files, offers and API keys; the free-text `log_activity()` stays and keeps feeding the Activity card (no removal). Audit screen with filters and CSV export; retention setting (default 2 years). API `GET /audit-log` scope `system:read`.
- Retention settings for contacts (inactive N years → anonymise), form submissions and orders (anonymise after N years) are opt-in and off by default.

## What remains?
1. Done (PR #738): `consent_log` + `audit_log` (11.0.16), person token and consent log helpers (`includes/fn/privacy.php`), `pg_audit()` (`includes/fn/audit.php`), tests; docs/memory/core/consent-log-and-audit-trail.md. No writer is hooked yet.
2. `pg_audit()` hooked into the listed save paths (M).
3. Export screen + JSON/PDF (M).
4. Erasure wizard with dry-run (M).
5. Audit screen, export, API; retention jobs (S–M).

## Open questions?
- Keep order e-mail for refund disputes after erasure? Keep the hashed token and the PSP reference only; refunds use the PSP reference.
- Which step hooks the consent writers? None of the steps above names them. `sms-and-iys.md` step 4 covers sign-up, account, checkout and unsubscribe for e-mail and SMS; the cookie notice's server endpoint and a `contract` row next to `order_documents` are not assigned.

## Where to start?
- Step 2; read `docs/memory/core/consent-log-and-audit-trail.md` (what `pg_audit()` keeps, which fields to leave out) and the ERP trail in `includes/erp/audit.php`. The plan's one `[NEW]` changelog line goes in with the first screen a manager uses (export or erasure).
