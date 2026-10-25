---
name: personal-data-erasure
description: privacy_erase.php (PR #1706): erasing one person after a dry run; how rows are found, what is deleted and what is kept with personal fields replaced, the token, the one audit line
---

## What is it?
- `privacy_erase.php` (manager+, Contacts menu, contact screen): one person erased for a KVKK/GDPR request, found as for the export (`pg_privacy_export_find()`). First a dry run; nothing is written until the "cannot be undone" box is ticked and confirmed. Engine `pg_privacy_erase()` (`includes/fn/privacy.php`), tests `tests/privacy_erase_test.php`.

## Where is the field list?
- `pg_privacy_erase_tables()`, one entry per table in the order it is done: `find` (column => scope list, OR), `action` (`delete` | `anonymise`), `fields` (column => new value). The test pins all three.
- `{name}` becomes `lang('Deleted customer')` (the operator's language), `{token}` the person token of the address the column holds; other values as given. An empty comment name is shown as "Anonymous".
- Account and contact go last, so a run stopped half-way still finds the person. A missing table or column is passed over.

## What stays, what goes?
- Kept, with personal fields replaced: orders, ship_tos, order_documents (IP and user agent only; the contract text stays), order_returns, gift_cards, erp_accounts with `is_person = 1`, their erp_invoices / erp_quotes / erp_waybills, comments, consent_log, ws_approval_guests.
- Deleted: uploaded files (row and disk), form_data (a form's fields and a product form's answers), form_signatures, forms, watchers, wishlist_items, stock_notify, email_recipients, mail_outbox, sms_outbox, chat_messages, chat_conversations, address_book, push_subscriptions, passkeys, identities, two-step and recovery codes, auth_tokens, opt_in, the contact's group memberships, contacts and user (with API devices and the rights `delete_users.php` removes).
- A company ERP account (`is_person = 0`) naming the person stays, with its invoices. A gift card the person bought loses only sender name and message (its recipient may still wait for it); one sent to them only the recipient address.

## How do the special tables work?
- `_pg_privacy_erase_apply_<table>()` for `files` (unlink), `gift_cards` (two parts), `consent_log` (an id-only row gets the token first), `user` (tokens, API devices, rights); the rest through `_pg_privacy_erase_write()`.
- A `{token}` column is written one stored address at a time (`pg_privacy_erase_token()`); a token already there stays.

## What does the audit trail keep?
- One line: `pg_audit('erase', 'person', 0, [], after, '')`, `after` = `person` (token), user and contact ids, counts under `deleted` / `anonymised`. No address, no name, no line per row (its before side would keep what was erased); the test checks for `pg_audit_record`, `pg_audit_fetch`, `pg_delete_file_record`. The site log line names nobody.

## Who may erase whom?
- The export's rule (`pg_privacy_export_allowed()`). Nobody may erase their own account (status `self`).

## What is not reached?
- Paraşüt's copy of synced contacts and invoices; Workspace customer channels (`ws_channels`, team talk about the contact); the site log and `visitors`. `audit_log` rows about the person are waiting on an owner question (`plans/privacy-data-pack-audit-log.md`).

## Sandbox (2026-10-25)?
- MariaDB 10.11, two people with a row in every table: erasing A (dry run by upper-case address, erasure by contact id) changed only A's rows and removed A's three files; B and the company account untouched; the dry run wrote nothing; a second run touched only the kept consents. Over HTTP: dry run, refusal without the box, erasure, one `panel` audit line, no name or address on the result.

Related: docs/memory/core/personal-data-export.md, docs/memory/core/consent-log-and-audit-trail.md, docs/memory/core/audit-trail-writers.md, docs/memory/plans/privacy-data-pack-audit-log.md
