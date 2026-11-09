---
name: list-table-variant-sets
description: The Variant Sets list (product_builder.php's pg_pb_variant_sets_screen()) on the list table driver, printed whole: columns, cells, bulk buttons, sort key, filters
module: variant_set_list
---

## How is the Variant Sets list drawn?
- `view_products.php?mode=variant_sets` runs `pg_pb_variant_sets_screen()` (`product_builder.php`). Since TD14b1 (#8226) it is a printed list on the driver (browser mode), the last screen that had a `table.chart`.
- Columns and cells: `includes/fn/variant_set_list.php` (`pg_variant_set_list_columns()`, `pg_variant_set_list_cells()`, test `tests/variant_set_list_test.php`). The name opens the set on `edit_product_group.php` (`data-pg-open`); the row's button duplicates it. Variants sort by count, price by the lowest price in minor units (a set without products by -1), Enabled by 1/0, Last Modified by its time.

## Where are its sort and bulk actions?
- List name `view_products_variant_sets` (`PG_VARIANT_SET_LIST`): its `pg_list_state()` key, so the products list beside it keeps its own sort; it is in `pg_list_table_printed_lists()`. The toolbar sort menu links back to `view_products.php` with `mode=variant_sets`.
- Enable, Disable and Delete sit in the driver's bar; the selection posts as `group_ids[]` (`ids_field`) in the one `form[name=form]` to `variant_set_action.php`, which checks each id is a variant set. Delete goes through the shared `Delete Selected` button router; Enable/Disable are the screen's own `.pg-vs-status` script.
- The status and parent filters stay a GET form in the toolbar (kept in the session); the list prints only when it has rows, the two empty states say why there is none.

Related: docs/memory/ui/list-table-printed.md, docs/memory/ui/list-table-driver-browser.md
