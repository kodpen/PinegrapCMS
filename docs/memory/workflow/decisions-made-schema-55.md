---
name: decisions-made-schema-55
description: Decisions made, schema part 55 (2026-11-27 on): automatic tax gets a rate class on the product and the country a line was taxed in on the order line
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-schema-54.md`.

## Which columns does automatic tax add? (owner, 2026-11-27, agent channel poll 248, message 14894)
- Asked after the count: the EU's VAT rate by the buyer's country needs no table (the rate list comes with updates, the setting starts off, a shop that types its own rate sees no change). Two further jobs need a column each, neither losing data: a reduced rate (books, food) needs a rate class on the product; the one-stop-shop report needs the country a line was taxed in, which is not stored today, so without it the report would guess.
- Owner: "İkisi de eklensin". Offered beside it and not chosen: only one of the two; neither.
- So, each by a step of the open version with the idempotent helpers:
  - `products.tax_class` (standard, reduced, zero; standard for every product stored today): `commerce/product-tax-class.md`.
  - `order_items.tax_country` CHAR(2), the member state the line was taxed in, empty on a line stored before: `commerce/tax-oss-report.md`. The report shows orders from before the column apart.
- His yes covers these two and nothing else.
- Running sites: a product keeps the rate it has; no stored order line is rewritten.
