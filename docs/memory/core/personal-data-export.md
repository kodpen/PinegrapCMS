---
name: personal-data-export
description: privacy_export.php (PR #938): one person's data as JSON and a PDF summary, how the person is found, what each section reads and masks, the role rule, the audit line
---

## What is it?
- `privacy_export.php` (manager+, Contacts menu, "Export personal data" on the contact screen): everything the site keeps about one person for a KVKK/GDPR request. JSON file = the full record; PDF summary (dompdf, as `erp_invoice_pdf()`) = the version sent to the person. Helpers: `includes/fn/privacy_export.php`; tests `tests/privacy_export_test.php`.
- The address is POSTed (with the CSRF token), never in the URL; the contact and user links pass only ids (`?contact_id=`, `?user_id=`). Refused without `audit_log` (`pg_audit_ready()`).

## How is the person found?
- `pg_privacy_export_find()`: users by id, address or `user_contact`; contacts by id or address (also the users' contacts); then the users of those contacts. One step only, so a shared company address does not pull in a second person through their own records. All addresses found are used for orders, forms, gift cards, watchers, mail and chat; `pg_privacy_export_merge()` is the pure part.

## What do the sections read?
- `pg_privacy_export_sections()` pins the 28 keys and their order (the file's format; the test fails on a change). Every section is written, empty ones too; each holds at most 5000 rows (`truncated` when cut).
- Orders include carts: an `order_items` line with `saved_for_later` is the wishlist. A product form's answers sit in the order row as `form_answers`; a form submission's fields as `fields`, found also by the address typed in any field (`form_data.data`, prefix index).
- Passkeys (`passkeys`, after `identities`, PR #TBD): id, user_id, name, aaguid, transports, backup flags, created_at, last_used_at. The credential (credential_id, credential_hash, public_key, user_handle, sign_count, alg) is never read; a test pins the columns.
- References only: ERP documents (number, date, totals, status) and Workspace customer channels. Not read: site log, `audit_log`, `visitors` (no index by order; IPs cleared by retention), Workspace guest rooms (a guest has only a name).

## What is masked?
- Every row is cleaned by `pg_privacy_export_clean()` when the pack is built (and by its collector): secret names by `pg_audit_secret_key()` (`***`, empty stays empty), plus per section: push endpoint/keys masked, signature strokes and timestamp token dropped, `claim_token` and `person_token` dropped, mail `properties` not read. Gift card codes keep their last four (`pg_privacy_export_code_hint()`). `ip_address` stored as INET_ATON becomes the address.

## Who may export whom?
- `pg_privacy_export_allowed()`: the sessions screen's rule. An administrator exports anyone; others themselves and users of a strictly lower role. A person with a higher or equal account is refused with a notice, the download too.

## What does the audit trail keep?
- Each download: `pg_audit('export', 'person', 0, [], after, label)`; label = first address, after = format, `person` (the person token under a name the trail does not mask), user and contact ids, counts of non-empty sections. The preview writes nothing.

## Sandbox (2026-10-20)?
- MariaDB, a person seeded in every section: found from the contact id and from an upper-case address (guest cart included), no secret in the JSON, PDF two pages with Turkish text, three audit lines; a manager refused for the administrator, allowed for a customer and themselves.

Related: docs/memory/core/consent-log-and-audit-trail.md, docs/memory/core/audit-trail-writers.md, docs/memory/plans/privacy-data-pack-audit-log.md, docs/memory/core/dompdf-embedding.md
