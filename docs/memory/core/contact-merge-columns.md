---
name: contact-merge-columns
description: pg_contact_merge_select(), the one SELECT list of both merge screens (#5325); the blank-column fill and the group opt-in rule of both merge branches
module: contacts
---

## Which columns does a merge read?
- `pg_contact_merge_select($optional)` (`includes/fn/contacts.php`, pure): one SELECT list for Merge Contacts (`view_contacts.php`) and Merge Selected (`edit_contacts.php`): every data column of the contact, the picture (`image`, `file_id`), `tax_number`, `tax_office`, `parasut_contact_id`, then `contact_user.user_id as user_id` and `contacts.timestamp`.
- Why one list? `merge_contacts()` fills a blank column of the contact that stays from the merged contact, so a column it did not read went with the deleted row. Before #5325 each screen listed its own columns and neither had the tax id or the IYS answers; Merge Selected had no picture either.
- `pg_contact_merge_optional_columns(pg_contact_merge_schema_columns())`: `iys_email_status` (11.0.39) and `iys_sms_status` (11.0.136) only when the schema has them. `erp_account_id` is never read: `pg_contact_holders_move()` sets it when the account moves.
- Test: `tests/contact_merge_columns_test.php`.

## What does the merge write into the contact that stays?
- `pg_contact_merge_filled_columns($keep, $merged, $exclude)` (pure): every column of the staying contact still at its default (`pg_contact_merge_value_blank()`: empty text, a zero number such as `file_id` 0 or a rate `0.00`, `0000-00-00`) takes the first non-blank value of the merged contacts, newest first. Both branches use it: without an account (leaving out `id`, `user_id`, `opt_in`, `timestamp`) and with one, the account's contact (also leaving out `user`, which that UPDATE sets).
- Why: the branch with an account wrote only `opt_in`, `timestamp`, `user`, so the merged contact's phone, company, tax id and IYS answers were deleted with it (#7600); the branch without one compared `'0' == ''`, so the picture (`file_id`) never moved (#7735).
- A subscription group's opt-in: `pg_contact_merge_group_opted_out()` counts only an explicit `0`; the LEFT JOIN's NULL (no `opt_in` row) is subscribed, as everywhere else. The branch without an account read `null == 0` as an opt-out and wrote an `opt_in = 0` row for the contact that stays (#7599).
- Test: `tests/contact_merge_fill_test.php`.

Related: docs/memory/core/contact-holders.md
