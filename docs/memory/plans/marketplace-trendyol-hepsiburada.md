---
name: marketplace-trendyol-hepsiburada
description: Plan (approved 2026-10-19): Trendyol (V2, first) and Hepsiburada connectors on the n11 layer; Trendyol steps 1 (#880), 2 (#906), 3 (#932), 4a (#1452), 4b (#1453), 4c (#<PR>) done; 2b or 5 next
---

## Why?
- Before this plan only n11 had a connector; Hepsiburada still appears only as a relay e-mail domain in `includes/api/outbound/orders.php`. Trendyol and Hepsiburada are the two largest Turkish channels and every competitor lists 8–30 marketplaces. A new connector is built on V2 only (webhooks, cursor stream, rate limits tied to listing limits, `channelId`, `paymentMethod`).

## What is decided?
- Order: Trendyol first, Hepsiburada second, each its own plan slice set; no intermediary integrator service (Pinegrap keeps its "no outside service" promise).
- Reuse everything the n11 work built: `base.php` contract, `marketplace_accounts` credentials blob, `marketplace_categories`, `marketplace_category_attributes`, `marketplace_category_map`, `marketplace_product_map`, `marketplace_order_map`, `marketplace_sync_queue` and `api_sync_job.php`. A new provider needs no schema change (`docs/memory/commerce/marketplace-*.md`); add columns only if a provider field has no home.
- Listings: category tree sync, required attribute mapping screen (same UI as n11), barcode required (`product_barcodes`), VAT mapping, price with marketplace commission shown read-only; listing status and rejection reasons stored per product map row.
- Stock and price push through the queue (5-minute job); Trendyol webhooks for order events where available, polling as fallback.
- Orders: one package = one import (n11 rule), order type `marketplace`, city/district mapping as fixed in `shipping-management.md` step 7; status push (picking, invoiced, shipped with carrier + tracking), invoice link push when the ERP e-document exists (`erp-auto-invoice-earchive.md`), cancellation sync.
- Returns: list, approve/reject, reason codes; buyer questions: list and answer from the panel; both as read-only first slices with later write actions.
- Payout/settlement reconciliation stays out (ERP roadmap B4, intentionally not done).

## What remains? (Trendyol; repeat for Hepsiburada)
1. ~~Driver, credentials, connection test, categories + attributes.~~ Done #880.
2. ~~Listing create, barcode and VAT checks, batch result.~~ Done #906.
2b. Listing update (V2 update needs `contentId`), approval/rejection status from the product filter services onto the map row (an approved listing sets an `error` map back to `active`: a stock line refused while awaiting approval stops the product), commission shown read-only (S–M).
3. ~~Stock/price queue.~~ Done #932. Its webhook receiver moved to step 4b: Trendyol webhooks carry only order statuses.
4a. ~~Order import (V2), Picking push, cancel sync, barcode/sku matching.~~ Done #1452.
4b. ~~Webhook receiver `marketplace_webhook.php`, registration on the account screen, job accepting webhook-imported packages.~~ Done #1453.
4c. ~~Own-carrier shipment push (alternative delivery).~~ Done #<PR>.
5. Invoice link push, returns, Q&A (M).
6. API fields: `/products/{id}/marketplaces`, webhook events `marketplace.order.*` (S).
7. Memory: each step adds what it verified to the `trendyol-*.md` files.

## Open questions?
- Hepsiburada developer portal renders as a JS shell; API facts must be collected from the owner's account documentation before slice 1.
- Trendyol "Luxe" channel listings: in scope only if the owner sells there.
- Owner: first live call with credentials; whether Kodpen registers as a Trendyol integrator (User-Agent `SelfIntegration` until then).

## Where are the finished steps' decisions?
- `docs/memory/commerce/trendyol-api-facts.md`, `trendyol-listings.md`, `trendyol-stock-price.md`, `trendyol-orders.md`, `marketplace-webhook-receiver.md`, `trendyol-own-carrier-shipping.md`. The `[NEW]` changelog line came with #932.

## Where to start?
- Step 2b or 5, after `trendyol-api-facts.md` and `trendyol-orders.md`. Live calls wait for the owner's test or live credentials.
