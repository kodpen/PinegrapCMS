---
name: marketplace-trendyol-hepsiburada
description: Plan (approved 2026-10-19): Trendyol (V2) and Hepsiburada on the n11 layer; Trendyol 1, 2, 2b-i, 2b-ii, 3, 4a-4c, 5a, 5b (with rejection), 5c, 6, A, B, common label done; Hepsiburada open
---

## Why?
- Before this plan only n11 had a connector. Trendyol and Hepsiburada are the two largest Turkish channels. A new connector is built on V2 only (webhooks, cursor stream, rate limits, `channelId`, `paymentMethod`).

## What is decided?
- Trendyol first, Hepsiburada second; no intermediary integrator service.
- Reuse the n11 layer: `base.php` contract, `marketplace_accounts` credentials blob, the `marketplace_*` category, map and queue tables and `api_sync_job.php` (`docs/memory/commerce/marketplace-*.md`). A new provider needs no schema change; add a column only if a provider field has no home.
- Stock and price through the queue (5-minute job); webhooks where available, polling as fallback.
- Orders: one package = one import, order type `marketplace`; status push (picking, invoiced, shipped); cancellation sync.
- Returns and buyer questions: read-only first slices, write actions later.
- Payout/settlement reconciliation stays out (ERP roadmap B4).

## What is done?
- Trendyol 1 driver and categories (#880), 2 listing create (#906), 2b-i approval status (#1539), 2b-ii listing update and commission (#1761), 3 stock/price queue (#932), 4a order import (#1452), 4b webhook receiver (#1453), 4c own-carrier shipping (#1493), 5a invoice link (#1747), 5b returns list and approval (#1772), 5b rest return rejection (#1837), 5c buyer questions and answers (#1784), 6 API endpoint and order events (#1667), A hardening (#1675), B schema 11.0.37 (#1687), common label (#3123: card #3257; order screen, bulk, reprint #3281; bulk buttons without a carrier service, `marketplace_carried` refusal #3568, #3594).

## What is open? (any session; claim one step at a time)
- Each step adds what it verified to the `trendyol-*.md` files, and reads `trendyol-api-facts.md` for the call rules first.
- Hepsiburada: the same slices, blocked until its API facts are collected (below).

## Open questions?
- Hepsiburada developer portal renders as a JS shell; API facts must come from the owner's account documentation before slice 1.
- Trendyol "Luxe" channel listings: in scope only if the owner sells there.
- Owner: first live call with credentials; whether Kodpen registers as a Trendyol integrator (User-Agent `SelfIntegration` until then).
- Unverified until a live call (listed in each `trendyol-*.md`): the `barcodes` comma list, whether a rejected listing re-enters approval on a new create, whether `docNeeded` turns false once the document is in.

## Where are the finished steps' decisions?
- `docs/memory/commerce/trendyol-api-facts.md`, `trendyol-listings.md`, `trendyol-listing-status.md`, `trendyol-stock-price.md`, `trendyol-orders.md`, `marketplace-webhook-receiver.md`, `trendyol-own-carrier-shipping.md`, `marketplace-api-and-events.md`, `marketplace-commission-and-invoice-columns.md`, `trendyol-invoice-link.md`, `trendyol-listing-update.md`, `trendyol-returns.md`, `trendyol-return-rejection.md`, `trendyol-questions.md`, `trendyol-common-label.md`. The `[NEW]` changelog line came with #932.

## Where to start?
- Owner (2026-10-24): no session holds this plan; any free session may take a step. Claim it as CLAUDE.md says (`[plan:marketplace-trendyol-hepsiburada] <step>`), one step per PR, steps of this plan one at a time.
- Next step: Hepsiburada once its API facts are in. No live credentials exist: build against saved answers in `tests/fixtures/trendyol/` shaped from the documentation, as the earlier steps did, and list what stays unverified.
- Read `docs/memory/commerce/README.md` first.
