---
name: personal-data-erasure
description: privacy_erase.php (PR #1706): erasing one person after a dry run; how rows are found, what is deleted and what is kept with personal fields replaced, the token, the one audit line
---

## What is it?
- `privacy_erase.php` (manager+, Contacts menu, contact screen): one person erased for a KVKK/GDPR request, found as for the export (`pg_privacy_export_find()`). First a dry run; nothing is written until the "cannot be undone" box is ticked and confirmed. Engine `pg_privacy_erase()` (`includes/fn/privacy.php`), tests `tests/privacy_erase_test.php`.

## Where is the field list?
- `pg_privacy_erase_tables()`, one entry per table in the order it is done: `find` (column => scope list, OR), `action` (`delete` | `anonymise`), `fields` (column => new value). The test pins all three.
- `{name}` becomes `lang('Deleted customer')` (operator's language), `{token}` the person token of the address the column holds. An empty comment name shows as "Anonymous".
- Account and contact go last, so a run stopped half-way still finds the person. A missing table or column is passed over.

## What stays, what goes?
- Kept, personal fields replaced: orders, ship_tos, order_documents (IP and user agent only; the contract text stays), order_returns, gift_cards, erp_accounts with `is_person = 1` and their invoices (issued ones too) / quotes / waybills, comments, consent_log, ws_approval_guests, audit_log. Owner's reasons: `workflow/decisions-made-product.md`.
- Deleted: uploaded files (row and disk), form_data (form fields, product form answers), form_signatures, forms, watchers, wishlist_items, stock_notify, email_recipients, mail and SMS queue, site chats, address_book, push_subscriptions, passkeys, identities, two-step and recovery codes, auth_tokens, opt_in, group memberships, contacts and user (with API devices and rights, as `delete_users.php`).
- A company ERP account (`is_person = 0`) naming the person stays, with its invoices. A gift card the person bought loses only sender name and message (its recipient may still wait for it); one sent to them only the recipient address.

## How do the special tables work?
- `_pg_privacy_erase_apply_<table>()` for `files` (unlink), `gift_cards` (two parts), `consent_log` (an id-only row gets the token first), `user` (tokens, API devices, rights); the rest through `_pg_privacy_erase_write()`.
- A `{token}` column is written one stored address at a time (`pg_privacy_erase_token()`); a token already there stays.

## What does the audit trail keep?
- One line: `pg_audit('erase', 'person', 0, [], after, '')`, `after` = `person` (token), user and contact ids, counts under `deleted` / `anonymised`. No address, name or line per row (its before side would keep what was erased; tested). The site log line names nobody.
- Earlier lines about the person (scope `audit_ids`: user, order and order_return lines by id, export lines whose label is their address): `pg_privacy_erase_audit_row()` replaces the columns of `pg_privacy_erase_audit_fields()` inside before/after and clears a user's or an export's label. Actor, time and action stay.

## Who may erase whom?
- The export's rule (`pg_privacy_export_allowed()`). Nobody may erase their own account (status `self`).

## What is not reached?
- Paraşüt's copy of synced contacts and invoices; Workspace customer channels (`ws_channels`, team talk about the contact); the site log and `visitors`.

## Sandbox (2026-10-25)?
- MariaDB 10.11, two people with a row in every table: erasing A changed only A's rows and files; B and the company account untouched; the dry run wrote nothing; a second run touched only the kept consents. Over HTTP: refusal without the box, erasure, one `panel` line, no name or address on the result. Audit lines (2026-10-26): A's user, order, return and export lines rewritten, B's untouched.

Related: docs/memory/core/personal-data-export.md, docs/memory/core/consent-log-and-audit-trail.md, docs/memory/core/audit-trail-writers.md, docs/memory/plans/privacy-data-pack-audit-log.md
