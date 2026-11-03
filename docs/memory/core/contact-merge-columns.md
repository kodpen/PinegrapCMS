---
name: contact-merge-columns
description: pg_contact_merge_select(): the one SELECT list both contact merge screens read, so no column of the merged contact is lost (#5325); the optional IYS columns
module: contacts
---

## Which columns does a merge read?
- `pg_contact_merge_select($optional)` (`includes/fn/contacts.php`, pure): one SELECT list for Merge Contacts (`view_contacts.php`) and Merge Selected (`edit_contacts.php`): every data column of the contact, the picture (`image`, `file_id`), `tax_number`, `tax_office`, `parasut_contact_id`, then `contact_user.user_id as user_id` and `contacts.timestamp`.
- Why one list? `merge_contacts()` fills a blank column of the contact that stays from the merged contact, so a column it did not read went with the deleted row. Before #5325 each screen listed its own columns and neither had the tax id or the IYS answers; Merge Selected had no picture either.
- `pg_contact_merge_optional_columns(pg_contact_merge_schema_columns())`: `iys_email_status` (11.0.39) and `iys_sms_status` (11.0.136) only when the schema has them. `erp_account_id` is never read: `pg_contact_holders_move()` sets it when the account moves.
- Test: `tests/contact_merge_columns_test.php`.

Related: docs/memory/core/contact-holders.md
