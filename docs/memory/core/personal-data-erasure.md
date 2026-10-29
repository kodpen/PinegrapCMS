---
name: personal-data-erasure
description: privacy_erase.php (PR #1706): erasing one person after a dry run; whose records are taken, what is deleted and what is kept with personal fields replaced, the checks, the audit lines
---

## What is it?
- `privacy_erase.php` (manager+, Contacts menu, contact screen): one person erased (KVKK/GDPR), found like the export (`pg_privacy_export_find()`). A dry run first; nothing is written until the "cannot be undone" box is ticked. Engine `pg_privacy_erase()` (`includes/fn/privacy.php`), tests `tests/privacy_erase_test.php`.

## Where is the field list?
- `pg_privacy_erase_tables()`, one entry per table (`table` names it when the key differs) in run order: `find` (column => scope list, OR), `action` (`delete` | `anonymise`), `fields`. The test pins them. `{name}` becomes `lang('Deleted customer')`, `{token}` the person token of the address the column holds; an empty comment name shows as "Anonymous".
- Orders, then contact and account, run last: the other records are found through them, so a stopped run still finds them again.

## Whose records are taken (`pg_privacy_erase_scope()`)?
- Orders by contact or billing address, by account only when not a counter sale (`type = 'local'` keeps the cashier in `user_id`).
- ERP accounts with `is_person = 1`: the contacts', those of orders that are the person's by contact or address, those naming the contact or address; never `ERP_WALKIN_ACCOUNT_ID`. Company accounts stay with their invoices.
- Form submissions by contact, or sent from the site by their account for nobody else (`contact_id = 0`, a visitor IP; panel and CSV records keep the staff member in `user_id` and no IP). Another record's field that holds the address only loses that value (`form_data_cells`). Signatures and uploads only of the person's own submissions.
- SMS by the person's own numbers (contacts, billing), not address book or shipments.
- Workflow table cells naming the person: `pg_privacy_wtable_rows()`; exported as `workspace` `table_row`, emptied on erasure (`ws_wtable_cells`), row kept (`workspace/workflow-tables.md`).

## What stays, what goes?
- Kept, personal fields replaced: orders, ship_tos, order_documents (IP and user agent; the text stays), order_returns, gift_cards (bought: sender name and message; received: the address), ERP accounts and their invoices (issued too) / quotes / waybills, comments, consent_log (id-only rows get the token first), ws_approval_guests, audit_log. Reasons: `workflow/decisions-made-product.md`.
- Deleted: uploads (row, disk), form_data, form_signatures, forms, `record_revisions`, watchers, wishlist, stock_notify, campaign recipients, mail and SMS queue, site chats, address_book, push, passkeys, identities, two-step, auth_tokens (API devices too), opt_in, group memberships, contacts, user (rights as `delete_users.php`).

## Who may erase whom?
- The export's rule (`pg_privacy_export_allowed()`), also applied to users whose `user_email` is one of the person's addresses but not tied to them; they are checked, not erased. Nobody erases their own account (status `self`).

## What does the audit trail keep?
- One line: `pg_audit('erase', 'person', 0, [], after, '')`, `after` = token, ids, counts under `deleted` / `anonymised`; no address or name, no line per row (tested).
- Earlier lines about the person (user, order, return by id; export by address; contact: `core/audit-trail-contacts.md`): `pg_privacy_erase_audit_row()` replaces the field list's columns in before/after and clears a user's or export's label. Actor, time and action stay.

## What is not reached?
- Paraşüt's copies, Workspace customer channels, the site log, `visitors`; an erased staff member's own workflow tables.

## Sandbox (2026-10-25/26)?
- MariaDB 10.11: only the person's rows and files changed, the dry run wrote nothing, a cashier's counter sales and the walk-in account stayed, a manager was refused on the admin's address.

Related: docs/memory/core/personal-data-export.md, docs/memory/core/consent-log-and-audit-trail.md, docs/memory/core/audit-trail-writers.md
