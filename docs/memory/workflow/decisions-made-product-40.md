---
name: decisions-made-product-40
description: Decisions list, product part 40 (2026-11-09 on): standing feature rules (legacy stays, global audience, no design variables); workflow tables like SQL tables; a product photo's alt text
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files.

## Which rules bind every feature plan? (recorded 2026-11-09)
- Owner (2026-10-19): plans never remove legacy code. Old payment gateways, the classic checkout, classic styles and renderers stay and keep working; new work goes beside them (frozen, not deleted). Retiring the legacy page designer was a decision of its own (`designer/page-designer-retired.md`).
- Owner (2026-11-02): the audience is mixed (bloggers, shops, agencies, corporate sites) and global: nothing is built around one country, and products or invoices are never put first.
- Owner (2026-11-06): design variables and classes are not built. Reason: with Bootstrap on the site output they would not pay.

## How do workflow tables behave like SQL tables? (2026-11-09)
- Owner (2026-11-09, as a selectable list in PR #8384; plan `plans/tables-relational.md`). The aim: tables that behave like SQL tables; a flow picks a row or works out values over the rows; related tables are read together, as a join reads them. Moving the product forward matters more than development time.
  - Row boxes find their row through a choice: the number as the table lists it, the id, or the last row. The ways were left to the session. Reason: the typed "1" was read as a hidden id and found nothing.
  - Values over the rows: the sum, the row count, the average, and the smallest and largest value.
  - A deleted row that other rows link to clears those links; the delete confirmation says how many rows are affected.
  - A link to a table the reader does not read shows no value. The cell reads as a row of a table they do not read, and the joined columns coming from that table stay empty. Reason: no data may leak.
  - A column type change is refused while any cell would not convert, and those cells are listed. Reason: no data is lost.
  - Keys: every table gets a stable number that counts up (1, 2, 3, never shifted by a delete). A column may also be made the key, which must be filled in and different on every row.

## What alt text does a product photo get? (2026-11-10)
- Owner (2026-11-10, asked as a selectable list, answered "Kısa açıklama olsun" and "ürünün adı aslında kısa açıklama"): on the product detail page the photo's alt text is the short description, the name the store shows, else the Product ID / SKU (`name`); on the first paint and after a variant is picked alike. Reason: `name` is the SKU; classic layouts already used the short description (`designer/variant-switch-page-parity.md`).
