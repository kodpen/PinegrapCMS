---
name: list-table-quick-edit-values
description: Which values of each driver list change in quick edit (products, pages, containers …), which stay text and why, how such an edit is checked, written and drawn (includes/fn/list_table_edit.php)
module: list_table_edit
---

## What is the rule?
- Owner (2026-11-04, `workflow/decisions-made-product-22.md`): every value the record's own edit screen can change is editable in quick edit, with that screen's checks and rights, on its `pg_list_edit_request()` endpoint. Computed values stay text: views, created/modified times, SEO score and impact, counts. Fixed: after the move only columns with an earlier `pg_list_edit` field were fields.

## Products: which cells change?
- `pg_pb_quick_edit_more_fields()` (product_builder.php), merged into `pg_pb_quick_edit_columns()`: the code (the ID column, field `name`, unique like the product screen: `pg_product_name_holder()`), taxable, product form title, selection type, default quantity (inside the product's min/max), shippable, weight (`decimal`, stored lb, four places), PWP, SWP, container, preparation, free shipping, extra shipping (minor units), Dim (`12 x 3 x 4`, x/×/*, the screen's checks per size, `pg_pb_quick_dimensions_change()`), the labelled custom fields, recurring, start (not with ClearCommerce), number of payments, payment period, allow to schedule, membership days, the two BCC addresses (validated). Shipping fields only while the store ships.
- Picked from the site (`pg_pb_quick_edit_choice_fields()`, the user's `pg_pb_quick_edit_choices()`: `select_page()`, private `select_folder()`, `select_contact_group()` (a role-3 user's own groups), none = 0): start page and private folder (only on a row that grants private access), e-mail page, contact group. Read only for a view showing one (`pg_product_list_source()`) or an edit of one (`view_products.php`). A cell whose stored id is not among the choices stays text (`pg_pb_quick_cell_editable()`), and the write refuses it.
- Still text, left on the product screen: allowed/disallowed zones (several choices) and the order receipt message (rich text); both need an input type the driver lacks (multi-select, multi-line). `tests/product_list_driver_test.php` fails for any other column that is not a field.
- The checks are pure (`pg_pb_quick_more_change()`); `pg_pb_quick_update()` writes them, stamps the change, logs, announces `product.updated`, tells a marketplace about the code and the weight too. The answer's cell is the list's own row read again (`pg_list_table_record_row()` with the session's view, then `pg_list_table_edit_answer()`), so the ID cell keeps its link.

## Pages: which cells change?
- Every column but SEO, impact and last modified: `list-table-quick-edit-pages.md`.

## Users: which cells change?
- Username, e-mail, role, start page and a User's rights: `list-table-quick-edit-users.md`.

## Contacts: which cells change?
- Names, company, phones, e-mail, opt-in and the address views' address parts: `list-table-quick-edit-contacts.md`. The groups stay text (several choices).

## Which other lists have quick edit?
- Short links, containers, menus, comments (all but the message), submitted forms (Status, Form Editor), themes (name, folder, activation), styles (a custom style's name, collection, layout type), design files (name, folder), shipping methods (all but the zones and Last Modified): each in `list-table-quick-edit-<list>.md` (`short-links`, `containers`, `menus`, `comments`, `submitted-forms`, `themes`, `styles`, `design-files`, `shipping-methods`), which says which cells change.

## Which lists are not done yet?
- Products: zones and order receipt message (above).
- Contacts: the groups (multi-select). Comments and themes: the message, the description (several lines).
- The commerce lists' columns outside their `pg_*_edit_columns()` (orders, gift cards, key codes, product attributes).

Related: docs/memory/ui/list-table-quick-edit.md, docs/memory/ui/product-list-paging.md
