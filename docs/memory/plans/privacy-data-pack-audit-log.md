---
name: privacy-data-pack-audit-log
description: Plan (approved 2026-10-19; steps 1, 2, 2b, 3 done: #738, #813, #905, #938): per-person export, erasure wizard keeping invoices, consent log, admin audit trail with before/after values
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
1. Done (PR #738): `consent_log` + `audit_log` (11.0.16), person token and consent log helpers (`includes/fn/privacy.php`), `pg_audit()` (`includes/fn/audit.php`), tests; docs/memory/core/consent-log-and-audit-trail.md. No writer is hooked yet.
2. Done (PR #813): `pg_audit_record()` in the save paths listed in `core/audit-trail-writers.md`.
2b. Done (PR #905): page editor, order screen, file manager, Recycle Bin, copies; JSON-text secrets masked. Still open there: user edit/delete, Workspace changes (S).
3. Done (PR #938): `privacy_export.php`, the JSON pack and the PDF summary; `core/personal-data-export.md`.
4. Erasure wizard with dry-run (M).
5. Audit screen, export, API; retention jobs (S–M).

## Open questions?
- A deleted order or user keeps its whole row in `audit_log.before_json`: anonymise it in step 4 or keep it for the trail's retention?
- Keep order e-mail for refund disputes after erasure? Keep the hashed token and the PSP reference only; refunds use the PSP reference.
- The export leaves out the site log, `audit_log` rows about the person and `visitors` (`core/personal-data-export.md`): add them in a later step?
- Which step hooks the consent writers? None of the steps above names them. `sms-and-iys.md` step 4 covers sign-up, account, checkout and unsubscribe for e-mail and SMS; the cookie notice's server endpoint and a `contract` row next to `order_documents` are not assigned.

## Where to start?
- Step 4, or the rest of 2b once #672 / #805 merge (`core/audit-trail-writers.md`). `core/personal-data-export.md` lists where a person's data lives and how they are found; erasure reuses `pg_privacy_export_find()`. The plan's `[NEW]` changelog line is in (export): step 4 widens it, no second line.
