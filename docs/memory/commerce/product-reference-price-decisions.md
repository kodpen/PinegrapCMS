---
name: product-reference-price-decisions
description: Reference price decisions: rule setting, coverage, compare-at only under manual, offers, POS, window end while a reduction runs (2026-10-27)
---

## Why is there a reference price?
- Turkish price label rules take the lowest price of the previous 30 days as the discount baseline; from 1 Aug 2026 discount advertising shows the lowest of the previous 10 days per sales channel. EU Omnibus art. 6a: 30 days. Before this work the storefront had only the offer strike-through and no history.

## What was decided (plan approved 2026-10-19)?
- The rule is a shop setting: `lowest_30d` (default, TR and EU), `lowest_10d` or `manual` (product-reference-price.md).
- The lowest of a window is read from the rows inside it plus the newest row before it, all sources: offer prices are recorded, not computed.
- While the `initial` row is younger than the window the label says "current price", never a lowest made up from part of the window.
- `compare_at_price` shows only under `manual` and only above the price shown (product-compare-at-price.md).
- Offers keep their discount display; the reference price stands beside it.
- POS: informational only there (it shows gross/net already); no POS output was built.

## Where does the window end while a reduction runs?
- Answered 2026-10-27. The owner told the coordinator "bekleyen işçilere yanıtı sen ver benim yerime", "en makul ve işlevsel seçimle" and "sorular mimari soru değilse makul seçim yap"; under that the plan's recommendation was taken.
- While a reduced price runs, the window ends where that reduced price began (the `changed_at` of the history row that started it); otherwise it ends now.
- Why: the TR price label rule and EU Omnibus art. 6a both name the lowest price of the period before the reduction. A window ending now holds the reduced price itself, so the advertised discount would be its own reference and show nothing.

## Where was it built?
- History, writers, API read, compare-at, offer rows, retention (PRs #658, #681, #1425), the reference function and window query (#1803), the rule setting (#2347), storefront tokens and the legacy catalog print (product-reference-price-storefront.md).

Related: docs/memory/commerce/product-reference-price.md, docs/memory/commerce/product-price-history.md
