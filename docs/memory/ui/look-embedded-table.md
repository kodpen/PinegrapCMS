---
name: look-embedded-table
description: Look A's list table inside an edit screen (SH8): .pg-embedded-table and its parts; the products table of Add and Edit Product Group on it, one row helper
---

## What is the recipe?
- Block "Look A: a list table inside an edit screen" in `backend.src.css`, after the figure cards. A plain table whose rows hold form fields (never on the list table driver, `list-and-table-screens.md`) takes `table table-hover mb-0 pg-embedded-table`.
- Heading row 38 px, 12 px (`--pg-text-meta`), weight 550, third text colour, a sorted heading in the text colour; rows at least 52 px, 6 / 14 px cell padding, a translucent line; cells on the card's ground. The same numbers as the driver's rows and SH3's `erp-list-table` (#9744).
- Parts: `.pg-embedded-table-tight` (a tick box or action column as narrow as its content), `.pg-embedded-table-number` (a 72 px end-aligned number field, with `pg-input pg-input-sm`), `.pg-embedded-table-foot` (the strip under the table, a line on top). Tick boxes `.pg-checkbox`, the row's action `a.pg-icon-button.pg-icon-button-sm` with `aria-label` naming the row.
- Phone (below 576 px): sides 8 px, a cell at least 6rem but the tight ones, a text cell (`.pg-embedded-table-text`: name, short description) 10rem so a three-word name wraps to two lines at most (architect, channel 25 message 5067); the table scrolls sideways in `.table-responsive`.

## Which screens use it?
- Add and Edit Product Group, the products card (`pg_product_group_form_products_card()`). Every row comes from `pg_product_group_form_row($item, $in_group, $image_cell)`: the group's products and child groups in `edit_product_group.php`, the offered ones in `pg_product_group_form_unselected_rows()`, and the Load More answer (`get_unselected_products`, `includes/panel/commerce.php`), which before SH8 had its own copy (sort order `maxlength` 4, decimal mask, no price `data-sort`).
- Select All is a bare `.pg-checkbox` in the heading (was a `.switch`); Load More is `pg-button pg-button-quiet pg-button-sm`, its script hides `.pg-embedded-table-foot` after the last batch. Edit is a link (was a button with `onclick`).
- Names keep their green or red and the Disabled word, as the products list on the driver does.

Test: `tests/look_embedded_table_test.php`.
