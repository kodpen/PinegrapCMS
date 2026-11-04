---
name: product-list-paging
description: The products list (view_products.php): views and their columns, the list table driver source, quick edit columns, bulk actions, search, barcode button, export
module: product_list
---

## Where does the list live now?
- `includes/fn/product_list.php`: the views (`pg_product_list_filters()`, condition per view, SEO views only once their columns exist), the columns a view shows in order (`pg_product_list_column_codes()`), the sort codes and their SQL, and one row per product.
- On the list table driver since TD7b: `pg_product_list_source()` (gate role < 3 or `manage_ecommerce`), `pg_product_list_columns($view, $quick_edit)` keyed by the old codes (a kept sort still names its column): `id` (the SKU as the `data-pg-open` editor link, with the SEO bar), `image`, the view's columns, `seo` (hidden at first, only where the view sorts by it), `actions` (edit, print barcode). The `product_list_rows` DataTables action is gone.

## How does quick edit work?
- The source's `edit` is `view_products.php` + `pg_pb_quick_edit_columns()`; the column keys are the edit fields (every value the product screen changes: `list-table-quick-edit-values.md`), cells carry `pg_list_edit_cell_attributes()`. A product that does not track stock gets no stock field. The write stays `pg_pb_quick_update()` on the screen's `pg_list_edit_request()`; the name cell (`data-pg-cell="name"`) follows the enabled flag in its answer.

## What do the bulk actions post?
- Modify Selected (its window fills the form's `edit_*` fields), Assign Barcodes and Delete Selected post `selection` + `list_params` to `edit_products.php`, which reads them with `pg_product_list_selected_ids()` (null above 10,000: refused, "Too many products are selected").

## What does the search box find?
- Name (SKU), short description, the user in "Last Modified", product form, custom fields shown, default quantity, tracked stock, selection type (by label), allowed zones, payment period, start page, private folder, order receipt BCC, e-mail page and BCC, contact group, as each view shows them.
- An amount: a word of digits, `.` and `,` is matched as the stored minor-unit digits against the price and the compare-at price (`pg_product_list_amount_term()`, a column with a `term` callable in `pg_list_search_where()`). Disallowed zones, check marks and dates are not searched.

## What does Last Modified show without a user?
- The time alone ("15 days ago"): `pg_list_when()` (`list_paging.php`) adds "by {var:1}" only for a non-blank user (products, Duplicate Products, commissions, Countries, Form Fields, Files).

## What else?
- Zones are read once per batch (`pg_product_list_zones()`). The barcode button carries its label data in `data-pg-barcode-product` (`pgPrintProductBarcode(id, button)`). Export reads the whole view with the same condition and sort (impact sorts by last change there).

## Why do sort codes never name a join?
- `export_products_f.php` reads `products` alone, and Export Products is a GET that keeps the session's sort. Contact Group, Set Start Page, Grant Private Access and E-mail Page sort by correlated subqueries for the name the cell shows (`(SELECT page_name FROM page WHERE page.page_id = products.email_page)`), so they work in the list and the export alike. Before, `contact_group_name` and `start_page` (SELECT aliases) made the export query fail ("Query failed."), `private_folder` and `products.email_page` sorted by id.
- Inventory Quantity sorts by `IF(products.inventory = '1', products.inventory_quantity, NULL)`, as the search does: an untracked product shows a dash and no longer sorts among the counts.
- The Users list's Start Page sorts by `start_page.page_name` (the join exists in every view offering that sort), not the page id `user.user_home`.
- Pinned by `tests/product_list_test.php` (no sort names a joined table or alias); the driver's impact column sorts by `pg_seo_impact_sql()` over the traffic join of its FROM. Driver columns and rows: `tests/product_list_driver_test.php`.
