---
name: orders-list-redraw-fixes
description: All Orders as landed after OL2 (#11142) against the drawing: four differences OL3 takes first (Products cell shows the SKU, Status badge, bar in two rows at 1440 px, six bar rows on a phone)
priority: 6
---

## What is this?
- All Orders (`view_orders.php`) opened on a sandbox at development 76e2da72, 1440 px and 390 px, Turkish, beside board A1 of `commerce/commerce-screens-drawing/orders.html` (`workflow/architect-looks-at-landed-screen.md`).
- OL3 (`plans/orders-list-redraw.md`) takes these four first, then its own work. Its close-out deletes this file.

## 1. Why does the Products cell show a code?
- It prints `order_items.product_name`, which is the SKU (`products.name`): a row reads "20M" where Summary's "Top 5 products" reads "Yeşil Ofis Sandalyesi".
- Fix: `pg_order_list_items_sql()` (`includes/fn/order_list.php`) reads the name people know, as Summary does: `COALESCE(NULLIF(products.short_description, ''), order_items.product_name)` over a LEFT JOIN on `order_items.product_id`, tags stripped. A deleted product falls back to the SKU. `tests/order_list_columns_test.php` gets the case.

## 2. Why are the three states in two looks?
- Status is still the filled blue badge (`badge theme-primary bg-gradient`); Payment and Shipping beside it are `pg_pill()` pills. The drawing shows three pills of one family.
- Fix: the order status is drawn with `pg_pill()` in the tones of `order-screen-redraw-states.md` (Incomplete grey, Complete and Exported teal, Cancelled rose). Its quick edit and the Paraşüt badge behave as today.
- The "Awaiting Payment" mark of the Status cell stays, as an amber pill: a person may have hidden the Payment column.

## 3. Why is the bar two rows at 1440 px?
- In Turkish the bar needs about 1447 px and has 1348 px: Sort, the status select, the type select and the filters button fall to a second row. The plan gives the second row only under 1280 px.
- Fix: the period reads with short month names and the year once when both days are in one year ("22 Eki – 22 Kas 2026"); the status select is at most 200 px wide, its text cut with an ellipsis (the full text in the open list).
- Checked in Turkish and English at 1440 px and 1280 px: one row.

## 4. Why does the list start so low on a phone?
- At 390 px the bar is six rows, about 290 px: Add Order and Export; Import Tracking Numbers; the period; Sort; the status select; the type select and the filters button.
- Fix, four rows: Sort stands at the end of the Import Tracking Numbers row; the status select, the type select and the filters button share one row, the status select taking the free width (`min-width: 0`).

## What is not a difference of this plan?
- Rows of 52 px with 16 px text, the boxed Action buttons, "1 hour ago" dates and no checkbox per row are the list table driver's, the same on every list (`ui/list-table-overview.md`). OL3 does not change them.
- "Nakliye" for Shipping and the other Turkish words: the drawing's Turkish does not bind.

Related: docs/memory/plans/orders-list-redraw.md, docs/memory/plans/orders-list-redraw-filters-panel.md, docs/memory/commerce/orders-list-columns.md
