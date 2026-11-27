---
name: ebay-orders
description: eBay orders read on the schedule (getOrders by last modification) into mp_import_order() in the order's own currency, and a parcel reported back with carrier and tracking number
module: ebay
---

## How do eBay orders come in?
- Only by the job's pull (`pull_orders`, `mp_ebay_pull_orders()`, `connectors/ebay.php`). No eBay order notice is subscribed and nothing reaches `marketplace_webhook.php`.
- `GET /sell/fulfillment/v1/order?filter=lastmodifieddate:[a..b]&limit=200&offset=n` with the seller's token (`sell.fulfillment` scope, asked since SC2).
- Window (`pg_ebay_order_window()`, pure): from the cursor minus 5 min, never older than 90 days less an hour, to now or the cursor plus a week. A first pull reads the last week; a backlog moves a week a pass.
- eBay does not sort by modification, so `read_to` is the window's end and the cursor moves only once every page was read (`next` or `total`). A page fault answers the packages read so far with `complete` false. 50 pages at most a pass.
- An account saved before SC6 has `pull_orders` 0: switch "Bring orders in" on in its settings.

## What is one package? (`pg_ebay_order_package()`, pure, `includes/fn/ebay.php`)
- One eBay order is one package: `orderId` is both `remote_order_id` and `remote_package_id`. `placed_at` is `lastModifiedDate`, the pull cursor's scale.
- Not paid yet (`orderPaymentStatus` PENDING or FAILED): no package. Paying changes the order, so it comes back.
- `remote_status` (`pg_ebay_order_status()`): CANCELED once the cancellation is granted, else `orderFulfillmentStatus` (NOT_STARTED, IN_PROGRESS = part shipped, FULFILLED). `mp_ebay_order_stage()`: CANCELED cancelled, FULFILLED shipped, the rest open. eBay reports no delivery.
- Currency: `pricingSummary.total.currency`, the order's own; the order keeps it (`order-charged-currency.md`).
- Line: `code` and `sku` = eBay `sku` (the product's name here, matched as n11's), price per unit = `lineItemCost` / quantity (eBay gives unit price × quantity), `discount_total` = the line's `appliedPromotions`, tax = seller-collected `taxes` per unit. `ebayCollectAndRemitTaxes` is eBay's money and is left out, as Trendyol's tax-inclusive price is kept.
- Shipping = `deliveryCost` less `deliveryDiscount`. Amounts by `pg_ebay_minor()`: two decimals, `round()`, a negative discount counts as its size.
- Buyer data: only the first shipping step's `shipTo` (name, address, phone, e-mail), which stands in as the billing address. `@members.ebay.com` is a relay address and matches no customer (`mp_order_email_is_personal()`).

## How is a shipment sent back?
- The eBay provider row says `ships_itself` (`mp_account_ships_itself()` true for every eBay account, no `delivery_since` needed) and `open_statuses` NOT_STARTED, IN_PROGRESS (`mp_ship_open_statuses()`; Trendyol keeps Created, Picking, Invoiced). So `mp_sync_ship_pending()` reports a parcel the shop records, as for a Trendyol account on its own carrier (`trendyol-own-carrier-shipping.md`).
- The parcel reaches the connector as `$package['parcel']` (`mp_sync_shipment_link()`: number, url, carrier key, carrier_name). `mp_ebay_ship_package()`: `POST /sell/fulfillment/v1/order/{orderId}/shipping_fulfillment` with every line and its quantity, `shippedDate`, `trackingNumber`, `shippingCarrierCode` (`pg_ebay_fulfillment_body()`).
- Carrier code (`pg_ebay_carrier_code()`): UPS, USPS, FedEx, DHL, DeutschePost, DPD, RoyalMail for the shop's list; else the carrier's name as the order screen shows it.
- Success writes `remote_status` FULFILLED (the connector's `data['remote_status']`, Trendyol's default Shipped). eBay's refusal goes to `last_error`; no answer, 429 or 5xx waits for the next pass.

## What is not verified?
- No live eBay call: no test keys, no seller account. Fixtures `tests/fixtures/ebay/orders*.json`, `fulfillment-error.json` follow eBay's Fulfillment API documentation (November 2026). Unverified: whether a carrier name off eBay's list is taken. Tests: `tests/ebay_orders_test.php`.
