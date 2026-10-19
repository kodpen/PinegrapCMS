---
name: marketplace-trendyol-hepsiburada
description: Plan (approved 2026-10-19, not started): Trendyol (V2 API, first) and Hepsiburada connectors on the n11 connector layer: listings, stock/price queue, orders, status, invoice link, returns, Q&A
---

## Why?
- `includes/api/outbound/connectors/` holds only `base.php` and `n11.php`; Trendyol and Hepsiburada appear only as relay e-mail domains in `includes/api/outbound/orders.php`. They are the two largest Turkish channels and every competitor lists 8–30 marketplaces. Trendyol retired its V1 order endpoints on 15 Oct 2026; a new connector is built on V2 only (webhooks, cursor stream, rate limits tied to listing limits, `channelId`, `paymentMethod`).

## What is decided?
- Order: Trendyol first, Hepsiburada second, each its own plan slice set; no intermediary integrator service (Pinegrap keeps its "no outside service" promise).
- Reuse everything the n11 work built: `base.php` contract, `marketplace_accounts` credentials blob, `marketplace_categories`, `marketplace_category_attributes`, `marketplace_category_map`, `marketplace_product_map`, `marketplace_order_map`, `marketplace_sync_queue` and `api_sync_job.php`. A new provider needs no schema change (`docs/memory/commerce/marketplace-*.md`); add columns only if a provider field has no home.
- Listings: category tree sync, required attribute mapping screen (same UI as n11), barcode required (`product_barcodes`), VAT mapping, price with marketplace commission shown read-only; listing status and rejection reasons stored per product map row.
- Stock and price push through the queue (5-minute job); Trendyol webhooks for order events where available, polling as fallback.
- Orders: one package = one import (n11 rule), order type `marketplace`, city/district mapping as fixed in `shipping-management.md` step 7; status push (picking, invoiced, shipped with carrier + tracking), invoice link push when the ERP e-document exists (`erp-auto-invoice-earchive.md`), cancellation sync.
- Returns: list, approve/reject, reason codes; buyer questions: list and answer from the panel; both as read-only first slices with later write actions.
- Payout/settlement reconciliation stays out (ERP roadmap B4, intentionally not done).

## What remains? (Trendyol; repeat for Hepsiburada)
1. Driver skeleton, credentials, connection test, category tree + attribute sync (M).
2. Listing create/update, barcode and VAT checks, status feedback (M).
3. Stock/price queue + webhook receiver `marketplace_webhook.php` with signature check (S–M).
4. Order import, status push, carrier/tracking push, cancel sync (M).
5. Invoice link push, returns, Q&A (M).
6. API fields: `/products/{id}/marketplaces`, webhook events `marketplace.order.*` (S).
7. Memory file `docs/memory/commerce/trendyol-api-facts.md` like `n11-api-facts.md`.

## Open questions?
- Hepsiburada developer portal renders as a JS shell; API facts must be collected from the owner's account documentation before slice 1.
- Trendyol "Luxe" channel listings: in scope only if the owner sells there.

## Where to start?
- Slice 1 after reading `n11-api-facts.md`, `marketplace-*.md` and the `pinegrap-dis-api` skill; test credentials from the owner's Trendyol seller account (stage environment).
