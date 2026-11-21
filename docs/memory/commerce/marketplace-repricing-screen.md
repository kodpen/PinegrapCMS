---
name: marketplace-repricing-screen
description: Marketplace Products › Repricing view (RP3b): figure cards, one segment row with Price log, lists on the table driver, Try first notice, rank on a product row
module: marketplace_repricing
---

## Where is the Repricing view?
- A fourth view of Marketplace Products (`marketplace_products.php?account=<id>&mode=repricing`), beside Catalogue, Products in this shop and Listings, on every marketplace once `mp_pricing_ready()`. Marketplaces' Matched products card leads there with a quiet small "Repricing" button.
- Architect (2026-11-20, agent channel message 11220): the door, the one segment row and the lists below.

## How is it laid out?
- The Try first notice (`.alert.theme-info.small`), the four figure cards (`.pg-figures`, `.pg-card.pg-figure`, each label a stretched link to its filter; Winning's value `is-good`, Losing's `is-bad` above 0), then ONE row of link segments (`nav.pg-seg.pg-list-seg`, `&view=`): All, Winning, Losing, At the floor, No cost with counts, and last Price log (`view=log`), which opens the log in the same place. The driver's search box sits on that row.
- Both lists are on the list table driver, read in keyset batches: `marketplace_repricing` (`mp_repricing_product_list_source()`) and `marketplace_repricing_log` (`mp_repricing_log_list_source()`), registered in `pg_list_table_definitions()`. Gate: Manager or above, the repricer tables, the account in `params`. Cards, filters and columns: `marketplace-repricing-lists.md`.

## Which reasons does the log hold?
- Architect (2026-11-20, message 11220): four plain strings in `marketplace_price_log.reason`, written by the robot (RP4), mapped to labels here with no shared constant: `lost` Buybox lost (amber), `floor` Floor reached (rose), `raised` Raised while winning (teal), `back` Stepped back (blue). An unknown code shows as itself in a grey pill.

## When does the Try first notice show?
- While `mp_repricing_try_first()`: the account's Pricing row has Try first on (`robot` 1) or the account has no Pricing row yet. Its Pricing link opens `marketplace_settings.php?account=<id>&settings=pricing`, the Settings drawer open on the Pricing tab.

## What does a product's marketplace row gain? (Products in this shop)
- Where the connector names `buybox`: rank 1 as a green pill "Winning", else "Buybox rank: N" (no ordinal ending) with the buybox price, never read as a grey pill "Not read yet" (`mp_repricing_rank_html()`). The last change: "Last change: old → new", reason pill, relative time.
- The floor there is `lang('Floor price')` (Taban fiyat): `lang('Floor')` is a building's floor in the coworking templates.

## What is tested?
- `tests/marketplace_repricing_test.php`: rank and others from `buybox_ours`, the floor rule, no cost, counts and card ids, the four reason strings and labels, the Try first notice, the rank pill, the view key and each card's SQL condition.

Related: docs/memory/commerce/marketplace-pricing-tab.md, docs/memory/commerce/marketplace-buybox-read.md, docs/memory/ui/look-marketplace-ranks.md
