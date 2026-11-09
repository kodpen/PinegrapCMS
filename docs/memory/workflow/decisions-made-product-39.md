---
name: decisions-made-product-39
description: Decisions list, product part 39 (2026-11-09 on): backup warning only after a month; carrier services at checkout; the standing feature rules (legacy stays, global audience, no design variables)
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

## Which rules bind every feature plan? (recorded 2026-11-09)
- Owner (2026-10-19): plans never remove legacy code. Old payment gateways, the classic checkout, classic styles and renderers stay and keep working; new work goes beside them (frozen, not deleted). Retiring the legacy page designer was a decision of its own (`designer/page-designer-retired.md`).
- Owner (2026-11-02): the audience is mixed (bloggers, shops, agencies, corporate sites) and global: nothing is built around one country, and products or invoices are never put first.
- Owner (2026-11-06): design variables and classes are not built. Reason: with Bootstrap on the site output they would not pay.
