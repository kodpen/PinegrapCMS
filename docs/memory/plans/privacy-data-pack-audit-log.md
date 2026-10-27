---
name: privacy-data-pack-audit-log
description: Plan (approved 2026-10-19; all steps done, open questions remain): per-person export, erasure wizard keeping invoices, consent log, admin audit trail with before/after values
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
5a. Done (#1821): `view_audit_log.php`, filters, CSV export; `core/audit-trail-screen.md`.
5b. Done (#1844): `GET /audit-log`, scope `system:read`; `core/audit-trail-screen.md`.
5d. Done (#1919): the cookie notice's answers into `consent_log`, `consent_answer.php`; `core/consent-log-cookie-notice.md`.
5e. Done (#2000): `contract` consent rows from `pg_legal_documents_store()`; `core/consent-log-writers.md`.
5c. Done (#2324): `config.audit_log_retention_years` (11.0.47, owner approved 2026-10-27), the general job's daily pass; `core/privacy-log-retention.md`.

## Open questions?
- The export leaves out the site log, `audit_log` rows about the person and `visitors` (`core/personal-data-export.md`): add them later?
- Tie an anonymous visitor's cookie rows by an id in `pg_consent` (cookie format change)? Asked in 5d's PR.

## Where to start?
- Every step is done; only the open questions above remain. Once the owner answers them, their decisions go to the topic files and this plan is deleted.
