---
name: customer-rhythm-steps
description: Plan customer-rhythm, steps CR1 to CR6: the store and nightly pass, the events with the brake, the reorder link, report and card, API and group rule, close-out
---

## Which steps are there?
- CR1 (M) first: the store and the nightly pass.
  - The store the architect chose (a migration step in the open version if a table); the privacy export and erase.
  - New `includes/fn/customer_rhythm.php`, pure: days per unit, own interval, the chance curve, stages and their moves, due products. The pass in the general job in bounded runs, reading `orders` and `order_items` with the Customers Report's key and counting rule.
  - `tests/customer_rhythm_test.php` red first: a two-pack bought at half the pace, a one-order customer, a stage that never goes back between orders, a rebuild that announces nothing, a subscription product, a marketplace order.
  - Timed on a 200k-order copy; the figure in the PR body. Features: H1 to H7, H16 (the pass).
- CR2 (M) after CR1: the two events through `pg_announce()`, the catalogue, payload fixtures, API reference blocks, the flow start box and its filters, the 7-day brake, the comparison share. Features: H8, H10, H12.
- CR3 (S) after CR2: the reorder link and the cart it fills. Feature: H9.
- CR4 (M) after CR1: the Rhythm part of the Customers Report (`view_customer_report.php`), list and CSV, the contact card's part, the settings. Features: H13, H14, H15.
- CR5 (S) after CR2 and CR4: `GET /reports/customer-rhythm` in `includes/api/resources/reports.php`, its type, `php tools/check_api_schema.php`; the contact group rule. Features: H11, H16.
- CR6 (S) close-out: every H number checked against the code with its PR; `commerce/customer-rhythm.md` written and `commerce/customer-value-report.md`'s left-out line updated; this plan's three files deleted; the changelog `[NEW]` line named in its PR.

## Which steps run side by side?
- After CR1: CR2 and CR4; CR3 after CR2. `includes/api/resources/reports.php` is also entered by `plans/sales-watch.md` SW5: check the open PRs before claiming.
