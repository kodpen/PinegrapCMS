---
name: decisions-made-product-39
description: Decisions list, product part 39 (2026-11-09 on): backup warning only after a month; carrier services at checkout; workflow tables like SQL tables (row choice, totals, links, keys)
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue, the date and the reason.

## When is a backup's age a warning? (2026-11-09)
- Owner (2026-11-09): warn only when the last backup is older than one month. Under a month, System Status reads the backup as fine (no warning, no score deduction, no greeting advice) and no operations alert is sent; the age may still be shown as plain information. No backup at all stays a failure; a switched-off schedule stays grey. Reason: the constant warning was noise; the agents kept writing "it has been 3 days since the last backup" into the daily summary, and the automatic backup is weekly, so the old two-day threshold was yellow most of every week. Rule: `platform/backup-age-rule.md`.

## How do carrier label services take part in checkout? (2026-11-09)
- Owner (2026-11-09, #8375, as a selectable list; plan `plans/shipping-checkout-rates.md`): a shipping method may name a carrier label service and charge its live price.
  - The label of such a method's order is made automatically once the order is paid (a card at once, a bank transfer when it is marked paid), by the general job, behind a switch per service that starts off, and tried once; never for an unpaid order. Reason: a live label costs money, and an unpaid order's label would be the store's loss.
  - Without a price (the service does not answer and no price up to 7 days old is kept) the method is not offered for that address, as the UPS/USPS live rates already do. Reason: the store never charges a fee lower than its cost.
  - Desi tariff tables for carriers without a price call (Aras direct, Basit Kargo, Kargonomi, a Yurtiçi contract) are a separate plan, later; those methods keep their fixed rates.

## How do workflow tables behave like SQL tables? (2026-11-09)
- Owner (2026-11-09, as a selectable list in PR #8384; plan `plans/tables-relational.md`). The aim: tables that behave like SQL tables; a flow picks a row or works out values over the rows; related tables are read together, as a join reads them. Moving the product forward matters more than development time.
  - Row boxes find their row through a choice: the number as the table lists it, the id, or the last row. The ways were left to the session. Reason: the typed "1" was read as a hidden id and found nothing.
  - Values over the rows: the sum, the row count, the average, and the smallest and largest value.
  - A deleted row that other rows link to clears those links; the delete confirmation says how many rows are affected.
  - A link to a table the reader does not read shows no value. The cell reads as a row of a table they do not read, and the joined columns coming from that table stay empty. Reason: no data may leak.
  - A column type change is refused while any cell would not convert, and those cells are listed. Reason: no data is lost.
  - Keys: every table gets a stable number that counts up (1, 2, 3, never shifted by a delete). A column may also be made the key, which must be filled in and different on every row.
