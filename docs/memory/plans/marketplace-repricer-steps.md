---
name: marketplace-repricer-steps
description: Plan marketplace-repricer, steps RP1 to RP6: tables, rules and floor, the account's Pricing part, the buybox read, the robot with try-first and log, API, close-out
---

## Which steps are there?
- RP1 (M) first: tables, the rules and the floor.
  - Migration step: the architect's tables or columns, idempotent helpers; types in the PR body.
  - New `includes/fn/marketplace_pricing.php` (pure: the rule pick, rounding, the floor from cost, VAT, commission, fee, shipping and margin); `mp_product_row()` sends the channel price with `listPrice` equal to it; channel rows into `product_price_history`.
  - `tests/marketplace_pricing_test.php`, red first: rule order, rounding endings, a floor with and without a listing commission, a product with no cost, the site price unchanged. Features: R1, R3, R4.
- RP2 (S) after RP1: the account's Pricing part (rules, step, margin, try-first) and the product's marketplace row (own price, strategy, floor shown); the one-time requeue on a rule change. Features: R2, R3, R8 (the switch).
- RP3 (M) after RP1: the buybox read in `includes/api/outbound/connectors/trendyol.php` (the documented body and header, checked against the fixture shapes, the limit), the hourly pass in the marketplace job, the stored state, the Repricing screen's lists. Features: R5, R11.
- RP4 (M) after RP2 and RP3: the robot pass: strategies, step, the day's limits, try-first, the log, the alert; tests for every strategy at and over the floor, the limits and a lost buybox. Features: R6, R7, R8, R9, R10, R12.
- RP5 (S) after RP4: the API routes, their types, `php tools/check_api_schema.php`. Feature: R13.
- RP6 (S) close-out: every R number checked against the code with its PR; `commerce/marketplace-repricer.md` written, `commerce/marketplace-price.md` updated; this plan's three files deleted; the changelog `[NEW]` line named in its PR.

## Which steps run side by side?
- After RP1: RP2 and RP3 side by side; RP3 touches only the connector, the job and the new screen.
- `connectors/base.php` and `trendyol.php` are entered by the marketplace plans too; check the open PRs before claiming RP1 and RP3.
