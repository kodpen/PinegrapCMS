---
name: list-table-driver-lists
description: Which screens are on the list table driver (pages, users, contacts, submitted forms, products, logs, the printed lists): sources, columns, bulk actions, what each keeps
module: list_table, submitted_form_list
---

## Which lists are on it?
- Pages (`view_pages`, `pg_page_list_source()`): `ui/page-list-paging.md`.
- Users (`view_users`, `pg_user_list_source()`, #6325): columns per view from `pg_user_list_columns($filter)`; the default view sorts by Last Modified through a hidden column of that name; `created` is `user.user_created` (0 for old accounts, so they stay in). Delete Selected posts `selection` + `list_params` to `delete_users.php` (`pg_user_list_selected_ids()`), which still skips the last administrator (the driver has no unselectable row).
- Contacts (`view_contacts`, `pg_contact_list_source()`, #6347): columns per view from `pg_contact_list_columns($filter, $shows_groups)` (picture first, as the editor link; the groups column only while its toolbar toggle shows them; the duplicates view leads with the e-mail and is printed whole, `all`, in `pg_contact_list_duplicates_order()`'s order). The session filters (dates, group, advanced boxes) come through `pg_contact_list_filter()`'s `tables`/`condition`. Organize, Opt-In, Opt-Out, Merge and Delete Selected post `selection` + `list_params` to `edit_contacts.php` (`pg_contact_list_selected_ids()`); Export, Merge and Delete All still act on the whole filter. No `created` (contacts have no creation time).
- Submitted forms (`view_submitted_forms`, `pg_submitted_form_list_source()`, #6366): the filters (date range, custom form, advanced filters, `query`) and the folder rule (`check_edit_access()` folders in the query) come from the session through `pg_submitted_form_list_filter()`'s `tables`/`condition`, as Export and Delete All Forms read them. The form's name links to it (`data-pg-open`); the contact sorts by `CONCAT(last_name, ' ', first_name)`; `created` is `forms.submitted_timestamp`. Delete Selected posts `selection` to `delete_submitted_forms.php` (`pg_submitted_form_list_selected_ids()`), which still checks each form's folder. A Payment column and an Unpaid filter join once the fee columns exist (`designer/custom-form-payment-staff.md`).
- Products (`view_products`, `pg_product_list_source()`, TD7b): the first list with quick edit (`ui/product-list-paging.md`). Modify Selected, Assign Barcodes and Delete Selected post `selection` + `list_params` to `edit_products.php` (`pg_product_list_selected_ids()`). No `created` (products keep only the last change).

- Orders, gift cards, key codes and shipping methods (TD8a), printed whole with quick edit: `ui/list-table-commerce-lists.md`.
- Printed whole in browser mode (`pg_list_table_printed()`, TD9a): menus (`view_menus.php`, the name opens the menu's items) and short links (`view_short_links.php`, the name opens the editor, a Visit column for a link with an address). No bulk actions.
- Printed whole, TD9b: themes, containers, comments (verified and question filters stay server filters; the box searches the rows), styles (Delete Selected posts `styles[]` to `delete_styles.php`; a classic style's "Open in the visual designer" is an actions column) and design files (Modify Selected and Delete Selected post `files[]` to `edit_files.php`; thumbnails through `data-pg-thumb`; a file in an archived folder is an italic row). The name opens the editor (`data-pg-open`).

- TD9c: `ui/list-table-region-lists.md`.
- Site Log, audit trail, firewall log (`view_log`, `view_audit_log`, `view_waf_log`, #6386): no bulk actions; `ui/site-log-paging.md`, `ui/audit-log-paging.md`, `ui/waf-log-paging.md`.

## What do they share?
- Each screen prints its source's first batch with `pg_list_table_html()`, a search box `data-pg-list-search="<list>"`, a hidden `selection` field filled from `pg-list-table:selection` and a `list_params` field; its bulk endpoint calls the list's `pg_<list>_list_selected_ids()` (`null` above 10,000 rows, refused with a "Too many … are selected" message).

Related: docs/memory/ui/list-table-driver.md
