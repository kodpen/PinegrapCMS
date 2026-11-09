---
name: marketplace-trendyol-hepsiburada
description: Plan (approved 2026-10-19): Trendyol (V2) and Hepsiburada on the n11 layer
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
- Trendyol, every slice and the common label (#880 to #3594): decisions in `commerce/trendyol-*.md` and `commerce/marketplace-*.md`; the `[NEW]` changelog line came with #932.

## What is open? (any session; claim one step at a time)
- Each step adds what it verified to the `trendyol-*.md` files, and reads `trendyol-api-facts.md` for the call rules first.
- Hepsiburada: the same slices, blocked until its API facts are collected (below).

## Open questions?
- Hepsiburada developer portal renders as a JS shell; API facts must come from the owner's account documentation before slice 1.
- Trendyol "Luxe" channel listings: not in 2026.11.0 (#3838); a later item, when a merchant asks and the Luxe API facts are gathered first.
- Owner: first live call with credentials; whether Kodpen registers as a Trendyol integrator (User-Agent `SelfIntegration` until then).
- Unverified until a live call (listed in each `trendyol-*.md`): the `barcodes` comma list, whether a rejected listing re-enters approval on a new create, whether `docNeeded` turns false once the document is in.

## Where to start?
- Owner (2026-10-24): no session holds this plan; any free session may take a step. Claim it as CLAUDE.md says (`[plan:marketplace-trendyol-hepsiburada] <step>`), one step per PR, steps of this plan one at a time.
- Next step: Hepsiburada once its API facts are in. No live credentials exist: build against saved answers in `tests/fixtures/trendyol/` shaped from the documentation, as the earlier steps did, and list what stays unverified.
- Read `docs/memory/commerce/README.md` first.
