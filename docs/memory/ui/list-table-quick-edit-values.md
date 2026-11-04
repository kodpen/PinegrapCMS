---
name: list-table-quick-edit-values
description: Which values of each driver list change in quick edit (products, pages), which stay text and why, how such an edit is checked, written and drawn (includes/fn/list_table_edit.php)
module: list_table_edit
---

## What is the rule?
- Owner (2026-11-04, `workflow/decisions-made-product-22.md`): every value the record's own edit screen can change is editable in quick edit, with that screen's checks and rights, on its `pg_list_edit_request()` endpoint. Computed values stay text: views, created/modified times, SEO score and impact, counts. The symptom it fixed: after the move only the columns that had a `pg_list_edit` field before were fields.

## Products: which cells change?
- `pg_pb_quick_edit_more_fields()` (product_builder.php), merged into `pg_pb_quick_edit_columns()`: the code (the ID column, field `name`, unique like the product screen: `pg_product_name_holder()`), taxable, product form title, selection type, default quantity (inside the product's min/max), shippable, weight (`decimal`, stored lb, four places), PWP, SWP, container, preparation, free shipping, extra shipping (minor units), the labelled custom fields, recurring, start (not with ClearCommerce), number of payments, payment period, allow to schedule, membership days, the two BCC addresses (validated). Shipping fields only while the store ships.
- Still text, left on the product screen: dimensions (three boxes in one cell), allowed/disallowed zones, start page, private folder, e-mail page, contact group (choices from the site; a role-3 user's group right), order receipt message (rich text). `tests/product_list_driver_test.php` fails for any other column that is not a field.
- The checks are pure (`pg_pb_quick_more_change()`); `pg_pb_quick_update()` writes them, stamps the change, logs, announces `product.updated`, tells a marketplace about the code and the weight too. The answer's cell is the list's own row read again (`pg_list_table_record_row()` with the session's view, then `pg_list_table_edit_answer()`), so the ID cell keeps its link.

## Pages: which cells change?
- Every column but SEO, impact and last modified: `list-table-quick-edit-pages.md`.

## Users: which cells change?
- Username, e-mail, role, start page and a User's rights: `list-table-quick-edit-users.md`.

## Which lists are not done yet?
- Contacts, submitted forms, menus, short links, themes, containers, comments, styles, design files, and the commerce lists' columns outside their `pg_*_edit_columns()`.

Related: docs/memory/ui/list-table-quick-edit.md, docs/memory/ui/list-table-quick-edit-pages.md, docs/memory/ui/list-table-quick-edit-users.md, docs/memory/ui/product-list-paging.md
