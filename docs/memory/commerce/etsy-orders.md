---
name: etsy-orders
description: Etsy orders by the scheduled pull (getShopReceipts) in the receipt's currency, a receipt without an address imported with a notice, a parcel reported with tracking code and carrier
module: etsy
---

## How do Etsy orders come in?
- Only by the job's pull (`pull_orders`, `mp_etsy_pull_orders()`, `connectors/etsy.php`); no Etsy webhook is subscribed (plan rule).
- `GET /shops/{shop}/receipts?min_last_modified=a&max_last_modified=b&was_paid=true&limit=100&offset=n`, the shop being the account's `shop` select (`transactions_r` scope, asked since ET1).
- Window (`pg_etsy_order_window()`, pure): from the cursor minus 5 min to now or the cursor plus a week; a first pull reads the last week. The cursor moves to the window's end only once every page was read (`count`); a page fault answers what was read with `complete` false. 50 pages at most a pass.
- An Etsy account saved before ET5 has `pull_orders` 0: switch "Bring orders in" on in its settings.

## What is one package? (`pg_etsy_receipt_package()`, pure, `includes/fn/etsy.php`)
- One receipt is one package: `receipt_id` is both `remote_order_id` and `remote_package_id`. `placed_at` is `updated_timestamp`, the pull cursor's scale. `is_paid` false: no package.
- `remote_status` (`pg_etsy_receipt_status()`): Etsy's status lower-cased, `completed` once `is_shipped` though Etsy still says paid. `mp_etsy_order_stage()`: canceled cancelled, completed shipped, the rest (refunds too) open.
- Money objects are amount over divisor (`pg_etsy_minor()`, `round()`); the currency is the grand total's `currency_code`, the order keeps it (`order-charged-currency.md`).
- Line: `code` and `sku` = the transaction's `sku`, price = its `price` (one unit), tax 0: the sales tax and VAT Etsy collects are Etsy's, as eBay's. The receipt's `discount_amt` is shared over the lines by value (`pg_etsy_discount_shares()`, largest remainder) as `discount_total`. Shipping = `total_shipping_cost`.
- Buyer: `name`, the address lines, `country_iso`, `buyer_email`; the address stands in as the billing address.

## What happens to a receipt without an address?
- An application made in Turkey gets no address fields (`sales-channels-global` plan). No `first_line` and no `country_iso` while a line is not digital: the package is imported without an address and carries `notice` "Etsy gives this application no address. Report its shipment on Etsy."
- The importer (`mp_import_claimed()`) keeps a package's `notice` in the map row's `last_error` once the order is written, as it keeps "Cancelled on the marketplace before it was imported". The Imported orders card shows it, and `mp_sync_ship_pending()` passes the package by. The base already took an order without an address (no `ship_tos` row), so no question to the architect was needed.

## How is a shipment sent back?
- The provider row says `ships_itself` and `open_statuses` paid, partially refunded, so `mp_sync_ship_pending()` reports a parcel the shop records, as for eBay (`ebay-orders.md`).
- `mp_etsy_ship_package()`: `POST /shops/{shop}/receipts/{receipt}/tracking`, form fields `tracking_code` and `carrier_name` (`pg_etsy_shipment_fields()`). Carrier (`pg_etsy_carrier_name()`): ups, usps, fedex, dhl, deutsche-post, dpd, royal-mail for the shop's list, else the carrier's name.
- Success writes `remote_status` completed. Etsy's refusal (an application without the call) goes to `last_error`; no answer, 429 or 5xx waits for the next pass.

## What is not verified?
- Built in ET5 of `plans/sales-channels-global.md` from Etsy's OpenAPI file; fixtures `tests/fixtures/etsy/receipts.json`, `receipt-shipped.json`, `error-shipment.json`; tests `tests/etsy_orders_test.php`. Unverified: Etsy's accepted carrier names, whether an address-less answer omits the fields or sends them empty (both read as none).
