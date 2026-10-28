---
name: contact-holders
description: pg_contact_holders(): the one list of every place a contact id is kept, shared by merge_contacts() and the erasure; what the merge does with each, the ERP refusal, the drift test
module: contacts
---

## What is it?
- `pg_contact_holders()` (`includes/fn/contacts.php`): one entry per place a contact's id is kept: `column`, optional `table` (when the key is not the table) and `type` (column => `'contact'` for a column that also names other records), `merge`, `erase`. Decided in #2421 (a).
- Holders: `orders`, `forms`, `email_recipients`, `erp_accounts`, `opt_in`, `contacts_contact_groups_xref`, `consent_log`, `user.user_contact`, `ws_channels.contact_id`, `ws_channels.customer_id` (type contact), `ws_refs`, `ws_approvals`, `ws_ai_changes` (typed record columns).

## What does the merge do with each?
- `move`: `pg_contact_holders_move()` re-points the merged contacts' rows to the contact that stays, before they are deleted. Before this, only groups and opt-in moved; orders, forms, ERP accounts, Workspace channels kept a deleted contact's id.
- `one` (`erp_accounts`): a group of duplicates where more than one contact has an ERP account is not merged at all (`pg_contact_merge_conflicts()`); `merge_contacts($contacts, &$refused)` counts it and both contact screens add a notice. One account only: re-pointed.
- `groups`: the merge's own contact group and opt-in step. `keep` (`consent_log`): left as it is, proof of what that record answered. `survivor` (`user`): a contact a user holds always stays.
- Tables a site may not have yet are skipped through `pg_schema_has()`.

## How is erasure tied to it?
- `erase`: `find` = `pg_privacy_erase_tables()` finds the table by `contact_ids`; `scope` = through `pg_privacy_erase_scope()` or the users; `none` = Workspace records, not reached (`core/personal-data-erasure.md`). The erasure keeps its own table list (per-table delete/anonymise fields); the test keeps the two in step.

## What does the test check?
- `tests/contact_holders_test.php`: every `contact_id` / `user_contact` column of the stock dumps and of every migration (`CREATE TABLE`, `install_add_column()`) is a holder and the other way round; typed holders name existing columns; the `find` holders equal the erasure's `contact_ids` tables. A new table with `contact_id` fails it until it is listed.
- Not covered by the test: a typed column under another name (`record_id` with a new kind). Add it by hand.

## What about the ERP mirror?
- `mirror` (`erp_accounts`: `contacts.erp_account_id`, as `erp_account_link_contact()` keeps it): set on the contact that stays when the account moves to it. #2591 moved the account without it, so the survivor showed no account (fixed in #2620).

## Which audit lines?
- `core/audit-trail-contacts.md`: a delete line per merged contact, one `merge` line (#2620).

Related: docs/memory/core/audit-trail-contacts.md, docs/memory/core/personal-data-erasure.md, docs/memory/plans/workspace-crm-seams.md
