---
name: list-table-own-code-screens
description: Screens with their own DataTables code moved off it (TD13b): All Offers on the driver with multi-value chip filters, the link picker printed, product group and File Manager tables plain
module: offer_list_edit
---

## How do the All Offers chips filter the driver?
- `view_offers` is a printed list (`pg_offer_list_row()`, `pg_offer_list_columns()`): code first (the name), the row's buttons last. Every chip is a driver filter button on column `enabled` (`data-pg-filter-column="enabled"`, All = '').
- An offer stands in three chip groups at once (status, how, incomplete), so the Status cell's `data-pg-filter` holds them separated by spaces (`pg_offer_list_filter()`: `active code incomplete`) and the driver keeps a row when one of them is the chip's value (`filterHas()`, Node test). A single value works as before (Site Log types).
- `recount()` counts the groups over every row (hidden ones too, all are printed); an optional chip shows while it has rows or is in use (`offers-list-chips.md`). A toggle that hides its row hands focus to the next row shown, else the last one, else the active chip.

## What did the driver gain for it?
- `pg-list-table:saved` on the root after a quick edit save (`detail`: `cell`, `row`, `data`), for a screen that follows the answer outside the cells.
- An answer cell's `sort` is written to `data-pg-sort-value`, so an edited date sorts by its new value.

## Which tables became plain, and why?
- The product group form's products table (`pg_product_group_form_products_card()`) holds the tick boxes and sort order fields the form posts, so it is a plain `table[data-pg-sort]` (`ui/list-and-table-screens.md`): DataTables' restricted mode had no paging or search anyway. Select All and its mixed state are the card's own script (rows from "Load More Products" included); price sorts by `data-sort`.
- The File Manager's file table (`includes/panel/explorer.php`) arrives in an answer after page load, so DataTables never started on it: the dead `chart`/`datatable-*` classes went, `text-nowrap` keeps one line per file.
- `includes/dashboard/widgets/widget_5.php` had no DataTables code left (a chart and a top pages list).

Related: docs/memory/ui/list-table-quick-edit-offers.md, docs/memory/ui/list-table-driver-sort.md, docs/memory/ui/list-table-printed.md
