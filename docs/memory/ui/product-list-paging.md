---
name: product-list-paging
description: The products list (view_products.php) paged on the server: views and their columns, quick edits on paged rows, search, barcode button, export, what moved where (PR #2655)
module: product_list
---

## Where does the list live now?
- `includes/fn/product_list.php`: the views (`pg_product_list_filters()`, condition per view, SEO views only once their columns exist), the columns a view shows in order (`pg_product_list_column_codes()`), the sort codes and their SQL, the headings and one row per product. The screen prints the headings from the same column list the row is built from, so the header and body cell counts cannot drift (the stock heading used to be set inside the row loop and vanished on an empty list).
- Action `product_list_rows` (`includes/panel/lists.php`), gate role < 3 or `manage_ecommerce`, reads the view from `filter` in `data-pg-list-params` and the sort from the session (`pg_list_state('view_products', …)`).
- The variant sets mode and the quick edit POST (`pg_list_edit_request()`) are untouched and still handled at the top of `view_products.php`.

## How do quick edits work on paged rows?
- A cell carries its attributes: `pg_list_cell($html, $class, $attributes)`; `pg_list_edit_cell_attributes()` gives the `data-pg-edit*` set (`pg_list_edit_cell()` prints the same). They travel in `DT_RowData.pgAttrs` by column number and `createdRow` sets them; the edit script listens on `document`, so paged cells edit in place as before.

## What does the search box find?
- Name (SKU), short description, the user in "Last Modified", product form, custom fields shown, default quantity, tracked stock, selection type (by label), allowed zones, payment period, start page, private folder, order receipt BCC, e-mail page and BCC, contact group, as each view shows them.
- An amount: a word of digits, `.` and `,` is matched as the stored minor-unit digits against the price and the compare-at price (`pg_product_list_amount_term()`, a column with a `term` callable in `pg_list_search_where()`). Disallowed zones, check marks and dates are not searched.

## What else changed with the paging?
- Counts and the page read join only one-row lookups (user, contact group, start and e-mail page, folder); the traffic join behind the impact score joins the page read only. Zones are read once per page (`pg_product_list_zones()`), not two queries per row.
- The barcode button carries its label data in `data-pg-barcode-product`; `pgPrintProductBarcode(id, button)` reads it (the page-wide `productMap` is gone).
- Export reads the whole view with the same condition and sort (impact sorts by last change there).

## Why do sort codes never name a join?
- `export_products_f.php` reads `products` alone, and Export Products is a GET that keeps the session's sort. Contact Group, Set Start Page, Grant Private Access and E-mail Page sort by correlated subqueries for the name the cell shows (`(SELECT page_name FROM page WHERE page.page_id = products.email_page)`), so they work in the list and the export alike. Before, `contact_group_name` and `start_page` (SELECT aliases) made the export query fail ("Query failed."), `private_folder` and `products.email_page` sorted by id.
- Inventory Quantity sorts by `IF(products.inventory = '1', products.inventory_quantity, NULL)`, as the search does: an untracked product shows a dash and no longer sorts among the counts.
- The Users list's Start Page sorts by `start_page.page_name` (the join exists in every view offering that sort), not the page id `user.user_home`.
- Pinned by `tests/product_list_test.php` (no sort names a joined table or alias). Select all, Modify Selected and Delete Selected act on the checked rows on screen, as before.
