---
name: list-table-driver-lists
description: Which screens are on the list table driver (pages, users, contacts, the three logs): their sources, columns, bulk actions through the resolver, what each keeps
module: list_table
---

## Which lists are on it?
- Pages (`view_pages`, `pg_page_list_source()`): `ui/page-list-paging.md`.
- Users (`view_users`, `pg_user_list_source()`, #6325): columns per view from `pg_user_list_columns($filter)`; the default view sorts by Last Modified through a hidden column of that name; `created` is `user.user_created` (0 for old accounts, so they stay in). Delete Selected posts `selection` + `list_params` to `delete_users.php` (`pg_user_list_selected_ids()`), which still skips the last administrator (the driver has no unselectable row). The `user_list_rows` action is gone.
- Contacts (`view_contacts`, `pg_contact_list_source()`, #6347): columns per view from `pg_contact_list_columns($filter, $shows_groups)` (picture first, as the editor link; the groups column only while its toolbar toggle shows them; the duplicates view leads with the e-mail and is printed whole, `all`, in `pg_contact_list_duplicates_order()`'s order). The session filters (dates, group, advanced boxes) come through `pg_contact_list_filter()`'s `tables`/`condition`. Organize, Opt-In, Opt-Out, Merge and Delete Selected post `selection` + `list_params` to `edit_contacts.php` (`pg_contact_list_selected_ids()`); Export, Merge and Delete All still act on the whole filter. No `created` (contacts have no creation time). The `contact_list_rows` action is gone.

- Site Log, audit trail, firewall log (`view_log`, `view_audit_log`, `view_waf_log`, #6386): no bulk actions; `ui/site-log-paging.md`, `ui/audit-log-paging.md`, `ui/waf-log-paging.md`.

## What do they share?
- Each screen prints its source's first batch with `pg_list_table_html()`, a search box `data-pg-list-search="<list>"`, a hidden `selection` field filled from `pg-list-table:selection` and a `list_params` field; its bulk endpoint calls the list's `pg_<list>_list_selected_ids()` (`null` above 10,000 rows, refused with a "Too many … are selected" message).

Related: docs/memory/ui/list-table-driver.md
