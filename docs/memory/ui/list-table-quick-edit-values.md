---
name: list-table-quick-edit-values
description: Which values of each driver list change in quick edit, which stay text and why, how such an edit is checked, written and drawn (includes/fn/list_table_edit.php)
module: list_table_edit
---

## What is the rule?
- Owner (2026-11-04, `workflow/decisions-made-product-22.md`): every value the record's own edit screen can change is editable in quick edit, with that screen's checks and rights, on its `pg_list_edit_request()` endpoint. Computed values stay text: views, created/modified times, SEO score and impact, counts. Fixed: after the move only columns with an older `pg_list_edit` field were.

## Products: which cells change?
- `pg_pb_quick_edit_more_fields()` into `pg_pb_quick_edit_columns()`: the code (ID column, field `name`, unique: `pg_product_name_holder()`), taxable, product form title, selection type, default quantity (inside the product's min/max), shippable, weight (`decimal`, lb), PWP, SWP, container, preparation, free shipping, extra shipping (minor units), Dim (`12 x 3 x 4`, x/×/*, the screen's checks per size, `pg_pb_quick_dimensions_change()`), the labelled custom fields, recurring, start (not with ClearCommerce), number of payments, payment period, allow to schedule, membership days, the two BCC addresses (validated). Shipping fields only while the store ships.
- Picked from the site (`pg_pb_quick_edit_choice_fields()`, the user's `pg_pb_quick_edit_choices()`: `select_page()`, private `select_folder()`, `select_contact_group()` (a role-3 user's own groups), none = 0): start page and private folder (only on a row that grants private access), e-mail page, contact group. Read only for a view showing one (`pg_product_list_source()`) or an edit of one (`view_products.php`). A stored id not among the choices stays text (`pg_pb_quick_cell_editable()`); the write refuses it.
- Allowed/Disallowed Zones (`multiselect`, with the choices while the store ships): two sides of one list; the row carries `allowed_zone_ids` (`pg_product_list_zones()`), values `pg_pb_quick_zone_value()`; written by `pg_pb_save_zones()`, as the product screen saves. The answer redraws the other side.
- Still text, left on the product screen: the order receipt message (rich text). `tests/product_list_driver_test.php` fails for any other column that is not a field.
- Checks pure (`pg_pb_quick_more_change()`); `pg_pb_quick_update()` writes, stamps, logs, announces `product.updated`, tells marketplaces of code and weight. The answer's cell is the list's own row read again (`pg_list_table_record_row()` with the session's view, then `pg_list_table_edit_answer()`), so the ID cell keeps its link.

## Pages: which cells change?
- Every column but SEO, impact and last modified: `list-table-quick-edit-pages.md`.

## Users: which cells change?
- Username, e-mail, role, start page and a User's rights: `list-table-quick-edit-users.md`.

## Contacts: which cells change?
- Names, company, phones, e-mail, opt-in, the address views' address parts and the groups: `list-table-quick-edit-contacts.md`.

## Which other lists have quick edit?
- Every other list with its own `list-table-quick-edit-<list>.md` (the `ui/README.md` index lists them): each says which cells change, which stay text and why. A new list adds its file, not a line here.

## How do several-line and several-choice values change?
- Field types `textarea` and `multiselect`: `list-quick-edit-keyboard.md`.

## Which lists are not done yet?
- Products: the order receipt message (above).
- Gift cards: the balance (the driver does not post the value a cell opened with) and store credit (`list-table-quick-edit-gift-cards.md`). Orders: nothing else the order screen saves is a list column (`list-table-quick-edit-orders.md`).
- Not a driver list: product groups, in the Catalog Manager (`view_folders.php`): renamed in its grid and tree, the rest on the group's screen.

Related: docs/memory/ui/list-table-quick-edit.md, docs/memory/ui/product-list-paging.md
