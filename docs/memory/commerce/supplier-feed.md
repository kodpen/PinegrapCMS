---
name: supplier-feed
description: Supplier feeds (Products › Supplier feeds, SF1 to SF7, #10771 to #11121): the store pulls suppliers' XML, CSV or JSON lists and keeps price and stock in step; F1 to F14, which file holds what
---

## What is it?
- The store pulls each supplier's XML, CSV or JSON list every 1 to 24 hours and keeps price and stock of matched products in step; records no product matched become drafts. Owner (2026-11-20, poll 168): `workflow/decisions-made-product-87.md`. Tables: `workflow/decisions-made-schema-38.md`.
- A recurring sync, not an import: its own tables, `imports` and its "nothing overwritten" rule untouched (`platform/import-framework.md`).

## What binds it?
- Every read through `api_http_request()`; address, user, password, header and key in one encrypted blob, only the host shown or returned.
- Stock only through `api_inventory_apply()`, price only through the product writer, so marketplaces, webhooks, price history and the audit trail see each change. A product a person turned off is never turned on by a feed.
- Works with Accounting off; ERP tables are only read (one ENUM value added, `supplier-feed-cost-api.md`).
- Architect (2026-11-20, channel message 10643): the "Price from the feed" switch and a held price live on the `supplier_feed_items` row, no `products` column; category and brand rules in the feed's rules JSON.
- Architect (2026-11-20, channel message 10643): a dropped product is closed in the same pull; the drop brake holds a mass drop; the price brake works both ways, under half or over ten times.
- Architect (2026-11-20, channel message 10643): http only per feed with "Allow an address without https" and a key in the address only; the feed's cost is a third, read-only cost source and keeps an order `estimate`.
- Architect (2026-11-20, channel message 10643): the screen sits behind `view_products.php`'s gate (role under 3 or manage_ecommerce); the API uses `products:read` / `products:write`.

## Which feature is where? (all built, checked 2026-11-22)
- F1 connection (sign-in ways, every 1/3/6/12/24 hours, currency, VAT, ERP account, http box) and F3 test pull: SF2 #10852, `supplier-feed-screen.md`.
- F2 field proposal (up to 200 records, Turkish and English names, record path): SF1 #10771 and SF2, `supplier-feed-readers.md`, `supplier-feed-screen.md`.
- F4 price rule (margin, fixed, rate, rounding, floor, category and brand rules): SF1 #10771 (pure) and SF3b #10963 (card 3), `supplier-feed-price-rule.md`, `supplier-feed-price-card.md`.
- F5 "Price from the feed" on the product's "Supplier feed" card: SF3b #10963, `supplier-feed-price-card.md`.
- F6 matching and writers, F7 closing and reopening, F12 run rows (90 days, 500 failed lines), F13 brakes and held prices with the `supplier_feed` operations alert: SF3a #10895, `supplier-feed-sync.md`; Accept, Reject, Pull now and Pull history: SF3b, `supplier-feed-price-card.md`.
- F8 drafts, pictures, groups by name, variant sets by model code, `dismissed`, "Drafts go into": SF4 #11010, `supplier-feed-drafts.md`.
- F9 several suppliers on one barcode, F10 prices worked out again after Update Exchange Rates: SF5 #11040, `supplier-feed-several.md`.
- F11 cost of an order line and the stock plan's supplier, F14 `GET /supplier-feeds`, `GET /supplier-feeds/{id}/runs`, `POST /supplier-feeds/{id}/run` with MCP tools: SF6 #11121, `supplier-feed-cost-api.md`.
- Not built: none.

## Which code holds it?
- `includes/fn/supplier_feed.php` (readers, proposal, price rule, pure), `supplier_feed_screen.php` (screens, download, test pull, product card), `supplier_feed_sync.php` (job tick), `supplier_feed_drafts.php`, `supplier_feed_choice.php` (several feeds, rates); `view_supplier_feeds.php`, `edit_supplier_feed.php`; `includes/api/resources/supplier_feeds.php`. Tests: `tests/supplier_feed*_test.php`.

## What is left out?
- Sending orders to the supplier, purchase invoices made from a feed, the store's own outbound feed for resellers, FTP or SFTP sources: each needs a new owner yes.
