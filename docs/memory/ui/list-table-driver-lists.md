---
name: list-table-driver-lists
description: Which screens are on the list table driver (pages, users, contacts, commissions, mail queue): their sources, columns, bulk actions through the resolver, what each keeps
module: list_table
---

## Which lists are on it?
- Pages (`view_pages`, `pg_page_list_source()`): `ui/page-list-paging.md`.
- Users (`view_users`, `pg_user_list_source()`, #6325): columns per view from `pg_user_list_columns($filter)`; the default view sorts by Last Modified through a hidden column of that name; `created` is `user.user_created` (0 for old accounts, so they stay in). Delete Selected posts `selection` + `list_params` to `delete_users.php` (`pg_user_list_selected_ids()`), which still skips the last administrator (the driver has no unselectable row). The `user_list_rows` action is gone.
- Contacts (`view_contacts`, `pg_contact_list_source()`, #6347): columns per view from `pg_contact_list_columns($filter, $shows_groups)` (picture first, as the editor link; the groups column only while its toolbar toggle shows them; the duplicates view leads with the e-mail and is printed whole, `all`, in `pg_contact_list_duplicates_order()`'s order). The session filters (dates, group, advanced boxes) come through `pg_contact_list_filter()`'s `tables`/`condition`. Organize, Opt-In, Opt-Out, Merge and Delete Selected post `selection` + `list_params` to `edit_contacts.php` (`pg_contact_list_selected_ids()`); Export, Merge and Delete All still act on the whole filter. No `created` (contacts have no creation time). The `contact_list_rows` action is gone.
- Commissions (`view_commissions`, `pg_commission_list_source()`) and recurring commission profiles (`view_recurring_commission_profiles`, `pg_commission_profile_list_source()`), #6380: no bulk actions; the affiliate name leads as the editor link; gate `pg_commission_list_allowed()` (role < 3 or `manage_ecommerce`). The commissions list's WHERE is the date range kept in the session (`ui/commission-list-paging.md`); its four totals now sit as cards above the list (the driver fills the window down to its foot). The `commission_list_rows`/`commission_profile_list_rows` actions are gone.
- Mail queue (`mail_queue` and `mail_queue_sms`, `pg_mail_queue_list_source()`/`pg_sms_queue_list_source()`, #6380): role <= 2 and the queue's table present; recipient first, newest first by default, now sortable by heading. Retry/Delete stay submit buttons of the screen's form (`ui/mail-queue-paging.md`). The `mail_queue_list_rows`/`sms_queue_list_rows` actions are gone.

## What do they share?
- Each screen prints its source's first batch with `pg_list_table_html()`, a search box `data-pg-list-search="<list>"`, a hidden `selection` field filled from `pg-list-table:selection` and a `list_params` field; its bulk endpoint calls the list's `pg_<list>_list_selected_ids()` (`null` above 10,000 rows, refused with a "Too many … are selected" message).

Related: docs/memory/ui/list-table-driver.md
