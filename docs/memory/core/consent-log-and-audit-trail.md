---
name: consent-log-and-audit-trail
description: consent_log and audit_log (11.0.16): the person token, consent row rules, what pg_audit() keeps (changed fields, masked secrets, size limits), who writes them yet
---

## What are the two tables?
- `consent_log`: one row per consent given or refused, appended only. `audit_log`: who changed what (panel, API, job) with the changed fields before and after. Both from `upgrade_2026_11_0_privacy_logs()` (11.0.16, PR #738); gates `pg_consent_log_ready()`, `pg_audit_ready()`. Helpers: `includes/fn/privacy.php`, `includes/fn/audit.php`.

## What is the person token?
- `pg_privacy_person_token($email)`: HMAC-SHA256 of `person:` + the trimmed, lower-cased address (`pg_privacy_normalize_email()`) under `ENCRYPTION_KEY`; `''` without an address or a key. It ties rows that outlive the person's records (consents kept as proof, anonymised orders) to the person. Export and erasure use the same function. A changed `ENCRYPTION_KEY` orphans every token.

## How is a consent row made?
- `pg_consent_log_record($event)`; `pg_consent_log_row()` validates. Channel and purpose from `pg_consent_log_purposes()`: `cookie` takes the notice's categories (`pg_consent_category_letters()`: analytics, marketing), `email`/`sms`/`call` take `marketing` (the İYS channels), `contract` takes the `order_documents.type` values (`preinfo`, `contract`, `kvkk`). Status `granted` or `refused`; a withdrawal is a refused row after a granted one. Source from `pg_consent_log_sources()`; a new writer adds its source there.
- A person (token, user or contact) is required except on `cookie`, which an anonymous visitor answers. ip is `waf_client_ip()` unless passed (pass `''` for imports and İYS pulls). A row that cannot be written returns 0 and stops nothing.
- Current answer: `pg_consent_log_current(pg_consent_log_rows($person))`, the newest row per `channel:purpose` (time, then id).

## Who writes consent rows today?
- Nobody yet. Planned writers: a server endpoint for the cookie notice (it still keeps the answer only in the `pg_consent` cookie), sign-up, checkout (legal documents, the KVKK box of `checkout-legal-documents.md` step 4), My Account, unsubscribe, İYS (`sms-and-iys.md` steps 4–5; the table those steps need is this one).

## What does pg_audit() keep?
- Only the fields that differ, compared as text (a DB row and a posted form disagree only on type). A side not given (before a create, after a delete) is `NULL`. An update that changed nothing writes nothing; an event given no fields (a key revoked) is always written.
- Secrets by field name (`pg_audit_secret_key()`: password, secret, token, key, api_key, `_enc`, `_hash`, card fields…) are stored as `***`, an empty value left empty, so set and cleared still show. Strings over 2000 bytes become `{truncated, length, sha1, start}` (200 bytes, UTF-8 safe); a side over 64 KB keeps only the field names (`pg_audit_limits()`).
- Actor (`pg_audit_actor()`): panel user, API app (owner id, app name) or `job`. `request_id` is the API's `X-Request-Id` inside the API, the ERP trail's id inside the ERP.
- Callers leave out fields that change on every save (modification time, last editor).

## Why these column choices?
- Labels are VARCHAR, not ENUM: a label added later must not become `''` on a server without its step.
- `audit_log` has `source`, `username` and `label` beyond the plan's list, like `erp_audit_log`: a deleted user or record keeps a name, API and job rows are told apart.
- Both keep their rows in a backup (records of who did what, like `log`); `tests/backup_test.php` pins it.

## Tests?
- `tests/privacy_test.php`, `tests/audit_log_test.php`. The writers were run against MariaDB 10.11 (quotes, Unicode, an unchanged update, a long text).

Related: docs/memory/plans/privacy-data-pack-audit-log.md, docs/memory/platform/cookie-consent-notice.md
