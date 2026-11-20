---
name: customer-rhythm-steps
description: Plan customer-rhythm, steps RY1a to RY6: the pure rules, the store and nightly pass, the events with the brake, the reorder link, report and card, API and group rule, close-out
---

## Which steps are there?
- RY1a (S) first, no schema: new `includes/fn/customer_rhythm.php`, pure: days per unit, own interval, the chance curve, stages and their moves, due products.
  - `tests/customer_rhythm_test.php` red first: a two-pack bought at half the pace, a one-order customer, a stage that never goes back between orders, a subscription product, a marketplace order. Features: H1 to H6 (the rules).
- RY1b (M) after RY1a, only after the owner's yes to poll 191 (message 10744): the store and the nightly pass.
  - The `customer_rhythm` table as a migration step in the open version, the columns of the schema decisions list; the privacy export and erase. The pass in the general job in bounded runs, reading `orders` and `order_items` with the Customers Report's key and counting rule; a rebuild announces nothing (test).
  - Timed on a 200k-order copy; the figure in the PR body. Features: H7, H16 (the pass).
- RY2 (M) after RY1b: the two events through `pg_announce()`, the catalogue, payload fixtures, API reference blocks, the flow start box and its filters, the 7-day brake, the comparison share. Features: H8, H10, H12.
- RY3 (S) after RY2: the reorder link and the cart it fills. Feature: H9.
- RY4 (M) after RY1b: the Rhythm part of the Customers Report (`view_customer_report.php`), list and CSV, the contact card's part, the settings. Features: H13, H14, H15.
- RY5 (S) after RY2 and RY4: `GET /reports/customer-rhythm` in `includes/api/resources/reports.php` (shared with sales-watch SW5: check the open PRs), its type, `php tools/check_api_schema.php`; the contact group rule. Features: H11, H16.
- RY6 (S) close-out: every H number checked against the code with its PR; `commerce/customer-rhythm.md` written and `commerce/customer-value-report.md`'s left-out line updated; this plan's three files deleted; the changelog `[NEW]` line named in its PR.

## Which steps run side by side?
- RY1a can start before the poll's answer. After RY1b: RY2 and RY4; RY3 after RY2. `includes/api/resources/reports.php` is also entered by `plans/sales-watch.md` SW5: check the open PRs before claiming.
