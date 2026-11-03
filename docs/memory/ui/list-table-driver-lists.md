---
name: list-table-driver-lists
description: Which screens are on the list table driver (pages, users, contacts, submitted forms, products): their sources, columns, bulk actions through the resolver, what each keeps
module: list_table, submitted_form_list
---

## Which lists are on it?
- Pages (`view_pages`, `pg_page_list_source()`): `ui/page-list-paging.md`.
- Users (`view_users`, `pg_user_list_source()`, #6325): columns per view from `pg_user_list_columns($filter)`; the default view sorts by Last Modified through a hidden column of that name; `created` is `user.user_created` (0 for old accounts, so they stay in). Delete Selected posts `selection` + `list_params` to `delete_users.php` (`pg_user_list_selected_ids()`), which still skips the last administrator (the driver has no unselectable row). The `user_list_rows` action is gone.
- Contacts (`view_contacts`, `pg_contact_list_source()`, #6347): columns per view from `pg_contact_list_columns($filter, $shows_groups)` (picture first, as the editor link; the groups column only while its toolbar toggle shows them; the duplicates view leads with the e-mail and is printed whole, `all`, in `pg_contact_list_duplicates_order()`'s order). The session filters (dates, group, advanced boxes) come through `pg_contact_list_filter()`'s `tables`/`condition`. Organize, Opt-In, Opt-Out, Merge and Delete Selected post `selection` + `list_params` to `edit_contacts.php` (`pg_contact_list_selected_ids()`); Export, Merge and Delete All still act on the whole filter. No `created` (contacts have no creation time). The `contact_list_rows` action is gone.
- Submitted forms (`view_submitted_forms`, `pg_submitted_form_list_source()`, #6366; paged on the server since #2509, issue #2038): the filters (date range, custom form, advanced filters, `query`) and the folder rule (`check_edit_access()` folders in the query) come from the session through `pg_submitted_form_list_filter()`'s `tables`/`condition`, as Export and Delete All Forms read them. The form's name links to it (`data-pg-open`); the contact sorts by `CONCAT(last_name, ' ', first_name)`; `created` is `forms.submitted_timestamp`. Delete Selected posts `selection` (no list parameters) to `delete_submitted_forms.php` (`pg_submitted_form_list_selected_ids()`), which still checks each form's folder. The `submitted_form_list_rows` action is gone. A Payment column and an Unpaid filter join once the fee columns exist (`designer/custom-form-payment-staff.md`).
- Products (`view_products`, `pg_product_list_source()`, TD7b): the first list with quick edit (`ui/product-list-paging.md`). Modify Selected, Assign Barcodes and Delete Selected post `selection` + `list_params` to `edit_products.php` (`pg_product_list_selected_ids()`). No `created` (products keep only the last change).

## What do they share?
- Each screen prints its source's first batch with `pg_list_table_html()`, a search box `data-pg-list-search="<list>"`, a hidden `selection` field filled from `pg-list-table:selection` and a `list_params` field; its bulk endpoint calls the list's `pg_<list>_list_selected_ids()` (`null` above 10,000 rows, refused with a "Too many … are selected" message).

Related: docs/memory/ui/list-table-driver.md
