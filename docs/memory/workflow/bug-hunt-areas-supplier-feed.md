---
name: bug-hunt-areas-supplier-feed
description: The bug hunt in supplier feeds SF1 to SF7 (k1, 2026-11-26, #11784): the pull, the readers, the amounts, the sync, several feeds, rights; five faults fixed, what was read clean, what is left
---

## Which area was it?
- Supplier feeds (`commerce/supplier-feed.md`): `includes/fn/supplier_feed.php`, `supplier_feed_sync.php`, `supplier_feed_choice.php`, the download in `supplier_feed_screen.php`, `includes/api/resources/supplier_feeds.php`.

## What was fixed? (#11784)
- JSON numbers: a float with three decimals (`12.375`) came out as the text `12.375`, which the amount reader takes as 12,375.00; on a first pull nothing holds it, so the selling price was a thousand times too high. The reader now writes it `12.3750`.
- A buy price of 0 was kept as the item's buy price, or counted as held with nothing held. It is now no buy price ("No buy price" line, last one kept).
- An item not followed (another feed carries the product) kept its buy price as read: gross of VAT beside the followed item's net one, and never held. A wrong price under half won the choice between feeds and was then written as the product's price. It now goes through `pg_supplier_feed_item_buy()` as a followed one.
- Two records of one feed on one product (by code and by barcode), in different steps, each took the product from the other: closed and reopened every pull. The feed's own items outside the step are now candidates (`pg_supplier_feed_choice_others()`).
- The pull downloaded into the screen's file and renamed it: a Test pull meanwhile cut or removed it and the brake paused the feed. The pull now downloads into its own file.
- Tests: `tests/supplier_feed_test.php`, `supplier_feed_sync_test.php`, `supplier_feed_choice_test.php`.

## What was read and found clean?
- The pull: every download through `api_http_request()` (address check, pinned address, no redirect followed, http only with the feed's box), 120 s timeout, 200 MB counted after decompression; the sign-in only in headers or the address, error lines carry the status or curl's text, the API and screen give the host only and draw secret boxes empty.
- XML: an external entity reads nothing (tried with a `file://` entity), `LIBXML_NONET`; a record path that matches nothing gives no records, and the empty-feed brake pauses; a cut file is `parse`, a brake.
- Cursors: XML by index, CSV by byte offset after the same reopening, JSON by index; none skips or repeats.
- VAT: `pg_supplier_feed_net_of_vat()` checked against the exact figure for every amount under 2,000.00 at 1, 8, 10, 18, 20 and five half rates under strict rounding: no difference.
- Price rule: ,90 and ,99 under the floor lifted to the next ending; held prices kept out of the choice, reprice and the product.
- Lock: `GET_LOCK` per feed, freed with the connection; a stopped pull goes on from `state`. Rights: `validate_ecommerce_access()` and the token field on both screens; `products:read` / `products:write` on the three routes.

## What is left?
- One code matching two products of the same name takes either one; whether to match none is a product choice, sent to the coordinator.
- "1.250" and "1,250" as text are 1250 by the decided rule; a VAT field written as a fraction (`0.18`) is read as 0.18 %.
- The selling price multiplies floats once (`pg_supplier_feed_price_parts()`), so a half minor unit may round apart on PHP 8.4; the rate is a float anyway.
- CSV and JSON reopen and read the whole file each step: slow on a big feed, not wrong.
- Pull now on a paused feed switches it on before the lock; if the job holds the lock the brake's bypass is lost and the next pull may pause it again.
