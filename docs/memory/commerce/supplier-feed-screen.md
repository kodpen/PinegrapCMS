---
name: supplier-feed-screen
description: Products › Supplier feeds (SF2): the list, Add and Edit supplier feed, the encrypted sign-in, the first read into the Fields mapping, the test pull; what each button writes
module: supplier_feed_screen
---

## Where are the screens?
- `view_supplier_feeds.php` (list) and `edit_supplier_feed.php` (Add and Edit), gate `validate_ecommerce_access()` (role under 3 or manage_ecommerce). Helpers: `includes/fn/supplier_feed_screen.php`.
- Reached from the Products menu panel ("Supplier feeds", last, after the catalogue entries) and the panel search; the row stays lit on both screens. The panel's links after its first group are one loop over screen, icon and label, so `tools/check_look.php`'s count for `output.php` fell (architect, 2026-11-20, channel message 11049).

## What does the list show?
- Printed whole (`pg_list_table_printed()`): name, host (never the address), format, Pulled every, Last pull with its pill, Products matched, and Edit / Test pull (quiet small), then the row's "more" menu with Delete (Design examples › Menus). Test pull and Delete post the feed's id as the button's value to the edit screen; Delete asks the shared confirm (`data-confirm-content`).
- No feed: one line on what a feed does and "Add supplier feed" (fill), no toolbar.

## How is the sign-in kept?
- Address, way (`none`, `basic`, `header`, `query`), user, password, header name, key and the key's name in the address are one blob in `supplier_feeds.secret` (`pg_supplier_feed_secret_encode()`, `encrypt_string_with_iv()`); only `host` is kept in the clear.
- Every secret box is drawn empty with "Saved. Leave empty to keep it."; an empty box keeps the saved value; another way drops the old way's fields (`pg_supplier_feed_input()`).
- An http address needs "Allow an address without https" (under Advanced) and only None or Key in the address (F1).

## What does "Read the feed" do?
- Saves cards Feed and Sign-in (a new feed is added with status 0), downloads through `api_http_request()` (120 s, 200 MB at most, decoded) to `data/temp/supplier_feed_<id>.dat`, deleted once read.
- Tells the format, lists the places records sit (`pg_supplier_feed_record_paths()`, the first is the one `pg_supplier_feed_record_path()` gives), reads 200 records, proposes the fields. Kept in `mapping` JSON: format, record_path, paths, keys (field => sample, 80 letters), fields, confirmed 0, records, read_at. A field a person saved before stays where the feed still has it.
- A read sets an on feed (status 1) back to 0 until the fields are saved again.

## What do Prices and Fields save?
- Shown only after a read. Prices: the feed's currency ('' the store's), prices include VAT, the ERP supplier account (only with Accounting on; `kind` supplier or both, active).
- Fields: a row a product field (Supplier code, Buy price, Stock required; the rest under "More fields"), a select of the feed's fields, the first record's value. A proposed choice wears "Suggested" until saved.
- Save sets `confirmed` 1 and an off or paused feed's status to 1 for the sync. Another record path in the select reads the feed again instead of saving the fields.

## What does a test pull count? (F3)
- Reads up to 50,000 records, matches by the code to `products.name`, else by `product_barcodes.barcode`. Added: no product. Closed: stock 0 on an open product (tracked: quantity over 0; untracked: enabled), or a product the feed's items follow missing from the pull. Reopened: stock back on a product the feed itself closed. Changed: a tracked product whose stock differs.
- The selling price is not compared: the price rule's form is not on the screen yet. Nothing is written; the result is drawn under the form, four figures and up to 20 rows each.

## What is not here yet?
- Pull now, the runs list, Accept and Reject of a held price and the F4 price rule's card: SF3b (architect, 2026-11-20, message 11049). Delete is also at the foot of the status column.

Related: `commerce/supplier-feed-readers.md`, `commerce/supplier-feed-price-rule.md`.
