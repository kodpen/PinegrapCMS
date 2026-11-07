---
name: classic-screens-fixes-2026-11
description: Classic (system-layout) storefront screen fixes: shipping method warnings, cart edit buttons and payments asterisk, catalog RSS top group, comments switch links, receipt labels and currency
---

## Why did the shipping method page warn on every system-layout render? (#7755)
`get_shipping_method.php` appended to `$output_products` and
`$output_shipping_method_rows` and printed `$output_requested_arrival_date`,
`$output_ship_to_header` and `$output_submit_button` without a start value
(each is filled only in some cases). All five now start `''`; the HTML is
the same.

## Why did cart items after the first lose their image edit buttons? (#7753)
`get_shopping_cart.php` called `get_form_info(..., $editable = FALSE, ...)`:
not a named argument but an assignment, so after the first item with a
product form `$editable` was false. Every root-screen `get_form_info()` call
now passes plain `false` (also the `$office_use_only = false` arguments,
which clobbered nothing that is read).

## Why did a custom cart layout show no asterisk on Number of Payments? (#7754)
The custom layout branch started `$number_of_payments_required = false` and
never set it. It is now true when card payment is on and the gateway is
ClearCommerce or First Data Global Gateway (the system layout's and
`get_order_form.php`'s condition), the case where `shopping_cart.php`
refuses an empty field; `includes/templates/shopping_cart.php` prints `*`.

## Why did a catalog page's RSS answer 404 without a product group? (#7743)
`catalog_pages.product_group_id` is `0` when none is chosen, and
`get_page.php` tested `== ''`, so it looked up group 0. It now tests
`(int) $product_group['id'] === 0` and falls back to the top-level group,
like `get_catalog.php`; the feed is then built as for that group.

## Why could the classic comments block fail on `?x[]=1`? (#7741)
The "Show All" / "Show Featured" links rebuilt the query with
`urlencode($value)` (TypeError on an array value) from
`parse_url(...)['query']` (undefined without a query). Both now use
`pg_comment_view_switch_url($request_uri, $view)` (includes/fn/comments.php):
splits at the first `?`, `parse_str()`, removes `comments`, sets
`comments=all` for `$view === 'all'`, `http_build_query()`; no `?` when the
query is empty. The caller adds `#software_comments` and `h()`. Test:
`test_comment_links_view_switch_url` (tests/comment_links_test.php).

## Why did the receipt say "Hediye Kartıs"? (#7738)
`get_order_receipt.php` and `get_express_order.php` appended a literal `'s'`
to `lang('Gift Card')` for several cards. They now print
`lang('Gift Cards')` when more than one card is applied, as the order
preview and the plain-text receipt do.

## Why was the receipt's Installment Charge in the base currency? (#7737)
The system layout of `get_order_receipt.php` used `pg_format_money(...,
BASE_CURRENCY_SYMBOL)`; it now uses `prepare_price_for_output(
$installment_charges * 100, ...)` like the other totals rows and the custom
layout (`$installment_charges` is the stored minor units / 100).
`get_view_order_screen_content.php` (My Account order view) still uses
`pg_format_money()` for that row.

## How was it checked?
`php -l` on every file, `php tools/test.php` (all green), `check_lang`.
Only the comments link helper has a unit test; the other fixes are in
procedural screen code and were not run in a browser.
