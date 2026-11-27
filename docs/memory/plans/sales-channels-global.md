---
name: sales-channels-global
description: Plan (owner 2026-11-27, poll 246): products listed and orders taken from Amazon, eBay, Etsy and TikTok Shop; starts with the census step SC1, the build follows the owner's answers
priority: 5
---

## What is asked?
- Owner (2026-11-27, poll 246, `workflow/decisions-made-product-117.md`): global sales channels. The list it belongs to and its rules: `plans/global-features.md`.
- For Turkey the same marketplaces were to come through an integrator (`plans/market-table-stakes.md`); his choice of 2026-11-27 is for sellers outside it.

## What is known today? (read from memory's lists on 2026-11-27, not from the code)
- Connectors for n11, Trendyol and Merchant Center stand on one base (`includes/api/outbound/connectors/`), with listings, category and attribute pairing, price and stock sent and orders taken in (`commerce/marketplace-listings.md`).

## Which steps? (S under 400 lines; each waits for the one before it)
- SC1 (S) Census, memory only, written as `plans/sales-channels-global-census.md` (more files if 4 KB is passed). It answers from the code and from the four marketplaces' own developer pages (no query carries the product's name), with file and line or address:
  - What the connector base gives a new marketplace and what n11 and Trendyol each had to add; which tables hold listings, pairings and orders, and whether they fit a marketplace with several countries and currencies.
  - For each of the four: how a seller's shop is connected, what its API asks for a listing, for stock and price, for an order and its shipment, and what registering an application there asks of the owner (review, fees, time).
  - A proposal: the order of the four by what each costs to build and to get approved, what each needs stored beyond today's tables, and the questions that are the owner's.
- The build's steps are written by the architect after the census and the owner's answers; nothing else of this plan is given out before.

## What does the census keep?
- No product code and no schema. Tables are asked of the owner one by one; an application registered at a marketplace is the owner's to make.
