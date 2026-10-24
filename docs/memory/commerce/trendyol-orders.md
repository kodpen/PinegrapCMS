---
name: trendyol-orders
description: Trendyol order packages: v2/orders pull, 14-day window and page caps, Awaiting/Verified skipped, UnPacked = cancelled, seller-only discount money, barcode/sku match, Picking push, unverified points.
---

## Which calls are used?
- Pull: `GET /integration/order/sellers/{sellerId}/v2/orders?startDate&endDate&orderByField=PackageLastModifiedDate&orderByDirection=ASC&page&size=200` (V2 mandatory since 15 Oct 2026). No `status` filter: a multi-status answer once came back without the status field (Aug 2025).
- Accept: `PUT …/shipment-packages/{packageId}` body `{"lines":[{"lineId","quantity"}],"params":{},"status":"Picking"}`; `params` is `new stdClass` (`{}`). Any 2xx is success, empty or not JSON (`mp_trendyol_call(…, 'PUT', true)`); other calls still read a non-JSON 2xx as an error. Package id and quantities come from the `$package` `mp_sync_approve()` passes third.
- Docs read 2026-10-24 (orders page modified 2026-10-07).

## How far does one pull read?
- Window: start `max(cursor − 300 s, now − 14 days + 1 h)`, end now, epoch ms. Two weeks is their maximum: an account paused longer loses older packages (Trendyol keeps a month; its seller panel shows them). A first pull reads a week. A new account starts with import on (as n11) and pulls once active; one saved between #1240 and #1452 has it off until switched on.
- 20 requests a pass; when 20 full pages do not pass the cursor, on up to 10,000 entries new to the pass (their window; page 49 is their last). Paging, `read_to` and `complete` as n11 (`n11-api-facts.md`, PR #1459). Every entry moves the cursor, skipped ones too.
- `orders/stream` (cursor, three months) is the way out if a window ever holds more than 10,000.

## Which packages are imported, and what do statuses mean?
- `Awaiting`/`Verified` (payment unconfirmed) are skipped; they return as `Created`.
- `Shipped`, `AtCollectionPoint`, `UnDelivered`, `Returned` → shipped; `Delivered` → delivered; `Cancelled`, `UnSupplied`, `UnPacked` → cancelled; the rest open. `UnPacked` = split: the goods move to new package ids, imported as new orders, so the old order gives its stock back or it is taken twice. A partial cancel is the same (old package Cancelled, the rest under a new id, same order number).
- `placed_at` = `lastModifiedDate`, not `orderDate`: "Try again" moves the cursor to the map row's timestamp.
- Auto-accept only for `remote_status` exactly `Created` (`marketplace-order-status-sync.md`).

## What money is imported?
- `price` = `lineGrossAmount` (unit, VAT inside), `tax` 0. `discount_total` = Σ `discountDetails[].lineItemSellerDiscount` (one per unit, exact); fallback `lineSellerDiscount` × quantity. Trendyol's discounts (`lineTyDiscount`, `lineItemTyDiscount`, `packageTyDiscount`) are not counted: they pay the seller as if none happened. Lines add up to `packageGrossAmount − packageSellerDiscount` (tested).
- Old names (`id`, `amount`, `discount`, `lineItemDiscount`, `merchantSku`) are read when the new ones are missing.

## How is a line matched to a product?
- `code` = barcode, `sku` = `stockCode`: mapping, barcode, then sku as the product name (`marketplace-order-import.md`).

## What is not done yet?
- Own-carrier shipping (`alternative-delivery`, 4c); `Invoiced` push and invoice link (step 5). Tax number/office are not stored (company only, on `commercial`). Packages also arrive by webhook: `marketplace-webhook-receiver.md`.

## What is unverified?
- No live call yet; fixtures follow the docs (`tests/marketplace_trendyol_orders_test.php`).
- Epoch ms vs the docs' "GMT+3". If their times are wall clock +3 h, packages arrive up to three hours late but none is lost, and order dates read three hours late.
- The body of a successful updatePackage; whether live lines carry `stockCode` or `merchantSku`.

Related: docs/memory/commerce/trendyol-api-facts.md, docs/memory/commerce/marketplace-order-import.md
