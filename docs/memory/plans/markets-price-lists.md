---
name: markets-price-lists
description: Plan (owner 2026-11-27, poll 246): markets, a price list a country and the charge in the buyer's currency; starts with the census step MK1, the build's steps follow the owner's answers
priority: 5
---

## What is asked?
- Owner (2026-11-27, poll 246, `workflow/decisions-made-product-117.md`): markets, a price list a country, the charge in the buyer's currency. The list it belongs to and its rules: `plans/global-features.md`.

## What is known today? (read from memory's lists on 2026-11-27, not from the code)
- A visitor chooses a currency and prices are shown converted by its rate (`designer/checkout-currency-picker.md`, `commerce/currency-form-rules.md`); the Stripe driver charges in the site's base currency.
- Wholesale gives a price by account (`commerce/wholesale.md`): the nearest thing to a second price list.

## Which steps? (S under 400 lines; each waits for the one before it)
- MK1 (S) Census, memory only, written as `plans/markets-price-lists-census.md` (more files if 4 KB is passed). It answers from the code, with file and line:
  - Every place a price is read to be shown or charged (product, variant, bundle, subscription, shipping, discount, gift card, store credit), and where the conversion is applied.
  - Where an order keeps its currency and rate, and what every reader of an order's totals assumes: refunds, reports, the API, the ERP entry, the marketplaces, the feeds.
  - In which currencies each payment driver can charge, from its own code and notes.
  - How wholesale's prices are stored and chosen, and whether a market's list can be laid the same way.
  - A proposal: the tables and columns a market and its price list need, each with its reason, what a site with one currency keeps, and the questions that are the owner's.
- The build's steps are written by the architect after the census and the owner's answers; nothing else of this plan is given out before.

## What does the census keep?
- No product code and no schema. A proposal is not a decision: tables are asked of the owner one by one.
- Prices are integer minor units; an amount in another currency is never worked out again from today's rate.
