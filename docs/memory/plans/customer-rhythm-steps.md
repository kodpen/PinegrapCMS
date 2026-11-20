---
name: customer-rhythm-steps
description: Plan customer-rhythm, steps CR1a to CR6: the pure rules, the store and nightly pass, the events with the brake, the reorder link, report and card, API and group rule, close-out
---

## Which steps are there?
- CR1a (S) first, no schema: new `includes/fn/customer_rhythm.php`, pure: days per unit, own interval, the chance curve, stages and their moves, due products.
  - `tests/customer_rhythm_test.php` red first: a two-pack bought at half the pace, a one-order customer, a stage that never goes back between orders, a subscription product, a marketplace order. Features: H1 to H6 (the rules).
- CR1b (M) after CR1a, only after the owner's yes to the plan's schema poll (its number written in by the architect): the store and the nightly pass.
  - The store as a migration step in the open version; the privacy export and erase. The pass in the general job in bounded runs, reading `orders` and `order_items` with the Customers Report's key and counting rule; a rebuild announces nothing (test).
  - Timed on a 200k-order copy; the figure in the PR body. Features: H7, H16 (the pass).
- CR2 (M) after CR1b: the two events through `pg_announce()`, the catalogue, payload fixtures, API reference blocks, the flow start box and its filters, the 7-day brake, the comparison share. Features: H8, H10, H12.
- CR3 (S) after CR2: the reorder link and the cart it fills. Feature: H9.
- CR4 (M) after CR1b: the Rhythm part of the Customers Report (`view_customer_report.php`), list and CSV, the contact card's part, the settings. Features: H13, H14, H15.
- CR5 (S) after CR2 and CR4: `GET /reports/customer-rhythm` in `includes/api/resources/reports.php`, its type, `php tools/check_api_schema.php`; the contact group rule. Features: H11, H16.
- CR6 (S) close-out: every H number checked against the code with its PR; `commerce/customer-rhythm.md` written and `commerce/customer-value-report.md`'s left-out line updated; this plan's three files deleted; the changelog `[NEW]` line named in its PR.

## Which steps run side by side?
- CR1a can start before the poll's answer. After CR1b: CR2 and CR4; CR3 after CR2. `includes/api/resources/reports.php` is also entered by `plans/sales-watch.md` SW5: check the open PRs before claiming.
