---
name: bug-hunt-areas-product-form-uploads
description: The bug hunt of product form file uploads (#10782, 2026-11-20): the two faults fixed, the issue opened, what was read clean
module: product_form_uploads
---

## Which area was hunted?
- Architect (2026-11-20, agent channel message 10776): product form uploads after FU1–FU5. `includes/fn/product_form_uploads.php`, `get_file.php` as it serves these files, the upload part of `cart_action.php`, `shopping_cart.php` and `express_order.php`, the sweep and the order readers.
- Left out as held: `includes/fn/ecommerce.php`, `bundles.php`, `stock_holds.php` (#10759), `includes/fn/widgets_catalog.php` (#10733).

## What did #10782 fix?
- A guest who had just placed the order was sent to sign in by the file link on the receipt page (classic receipt, designed order view): the session's cart is gone by then. `pg_pfu_access_verdict()` now also takes `completed_order_id`, as `pg_legal_document_may_view()` does.
- The designer cart and express order showed a file deleted from Files as kept: a dead link, and a required upload read as answered while Purchase Now asked for it again. `pg_pfu_kept_file()` drops an answer whose `files.size` comes NULL from the LEFT JOIN.

## Which issue did it open?
- #10776: a quantity the cart lowers itself (`check_inventory()`, the minimum/maximum clamp, `check_reservations()`, bundles) keeps the files of the dropped units; held files, so not fixed here.

## What was read clean?
- Type and size: the allow-list only narrows the blocklist (every inner extension), the last extension of the sent name is checked, the size cap never goes above the server's, upload errors and an oversize body are refused before the token.
- Another person's file: every cart path checks that the line is the session's order; get_file.php decides before the folder check, sends attachments `private` and HTML/SVG sandboxed; folder views and galleries leave attachments out.
- Removal: every line, unit, cart reset and order delete path takes its files first, keeping a file another answer still holds.
- The sweep: only `incomplete` carts, the last touch counts the newest upload, the delete re-reads the status and stamps.
- Readers: names escaped, e-mails carry the name only, the API's file is null once the file is gone.

## What was not run?
- No database here: both fixes are tried by unit tests of the pure rules.
