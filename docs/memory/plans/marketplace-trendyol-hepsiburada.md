---
name: marketplace-trendyol-hepsiburada
description: Plan (approved 2026-10-19): Trendyol (V2, first) and Hepsiburada connectors on the n11 layer; Trendyol step 1 done #880, step 2 (listings) next
---

## Why?
- Before this plan `includes/api/outbound/connectors/` held only `base.php` and `n11.php`; Hepsiburada still appears only as a relay e-mail domain in `includes/api/outbound/orders.php`. They are the two largest Turkish channels and every competitor lists 8–30 marketplaces. Trendyol retired its V1 order endpoints on 15 Oct 2026; a new connector is built on V2 only (webhooks, cursor stream, rate limits tied to listing limits, `channelId`, `paymentMethod`).

## What is decided?
- Order: Trendyol first, Hepsiburada second, each its own plan slice set; no intermediary integrator service (Pinegrap keeps its "no outside service" promise).
- Reuse everything the n11 work built: `base.php` contract, `marketplace_accounts` credentials blob, `marketplace_categories`, `marketplace_category_attributes`, `marketplace_category_map`, `marketplace_product_map`, `marketplace_order_map`, `marketplace_sync_queue` and `api_sync_job.php`. A new provider needs no schema change (`docs/memory/commerce/marketplace-*.md`); add columns only if a provider field has no home.
- Listings: category tree sync, required attribute mapping screen (same UI as n11), barcode required (`product_barcodes`), VAT mapping, price with marketplace commission shown read-only; listing status and rejection reasons stored per product map row.
- Stock and price push through the queue (5-minute job); Trendyol webhooks for order events where available, polling as fallback.
- Orders: one package = one import (n11 rule), order type `marketplace`, city/district mapping as fixed in `shipping-management.md` step 7; status push (picking, invoiced, shipped with carrier + tracking), invoice link push when the ERP e-document exists (`erp-auto-invoice-earchive.md`), cancellation sync.
- Returns: list, approve/reject, reason codes; buyer questions: list and answer from the panel; both as read-only first slices with later write actions.
- Payout/settlement reconciliation stays out (ERP roadmap B4, intentionally not done).

## What remains? (Trendyol; repeat for Hepsiburada)
1. ~~Driver skeleton, credentials, connection test, category tree + attribute sync (M).~~ Done #880 (`connectors/trendyol.php`; fixtures only, no live call yet).
2. Listing create/update, barcode and VAT checks, status feedback (M).
3. Stock/price queue + webhook receiver `marketplace_webhook.php` with signature check (S–M).
4. Order import, status push, carrier/tracking push, cancel sync (M).
5. Invoice link push, returns, Q&A (M).
6. API fields: `/products/{id}/marketplaces`, webhook events `marketplace.order.*` (S).
7. Memory file `docs/memory/commerce/trendyol-api-facts.md`: started with #880; each step adds what it verified.

## Open questions?
- Hepsiburada developer portal renders as a JS shell; API facts must be collected from the owner's account documentation before slice 1.
- Trendyol "Luxe" channel listings: in scope only if the owner sells there.

## What did step 1 decide?
- Credentials (seller id, key, secret, live/test environment) live in the encrypted `credentials` JSON; the environment is a select field (`mp_providers()`). User-Agent integrator name `SelfIntegration` until Kodpen registers as a Trendyol integrator.
- The category cache stays per provider: live and test are assumed to share category ids.
- `[NEW]` changelog line waits for the step that makes Trendyol usable (stock/price or orders).

## Where to start?
- Step 2 (listings: brand id, barcode, VAT, `slicer`, V2 create + batch result) after `trendyol-api-facts.md` and `marketplace-listings.md`. First live call (step 1's test and category fetch) waits for the owner's test or live credentials.
