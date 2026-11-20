---
name: marketplace-repricer-steps
description: Plan marketplace-repricer, steps RP1 to RP6: tables, rules and floor, the account's Pricing part, the buybox read, the robot with try-first and log, API, close-out
---

## Which steps are there?
- RP1 (M) first (the owner said yes to poll 186): tables, the rules and the floor.
  - Migration step: the three tables (`marketplace-repricer.md`), idempotent helpers; the columns of channel message 10721, checked against the code, in the PR body, asked of the architect before ready.
  - New `includes/fn/marketplace_pricing.php` (pure: the rule pick, rounding, the floor from cost, VAT, commission, fee, shipping and margin); `mp_product_row()` sends the channel price with `listPrice` equal to it; `rule` rows into `product_price_history` on the account's channel (`commerce/product-price-history.md` corrected if its `<code>` names the provider); the floor's rates from the order-profit plan's reader.
  - `tests/marketplace_pricing_test.php`, red first: rule order, rounding endings, a floor with and without a listing commission, a product with no cost, the site price unchanged. Features: R1, R3, R4.
- RP2 (S) after RP1: the account's Pricing part (rules, step, margin, try-first) and the product's marketplace row (own price, strategy, floor shown); the one-time requeue on a rule change. Features: R2, R3, R8 (the switch).
- RP3a (M) after RP1: the buybox read as a connector ability in `includes/api/outbound/connectors/trendyol.php` (the documented body and header, checked against the fixture shapes, the limit), the hourly pass in the marketplace job, the stored state. Feature: R5.
- RP3b (S) after RP3a: the Repricing screen's lists and the product's marketplace row. Feature: R11.
- RP4 (M) after RP2 and RP3a: the robot pass: strategies, step, the day's limits, try-first, the log, the alert codes in `pg_ops_alert_codes()` (the sales watch's steps enter the same file: check the open PRs); tests for every strategy at and over the floor, the limits and a lost buybox. Features: R6, R7, R8, R9, R10, R12.
- RP5 (S) after RP4: the API routes, their types, `php tools/check_api_schema.php`. Feature: R13.
- RP6 (S) close-out: every R number checked against the code with its PR; `commerce/marketplace-repricer.md` written, `commerce/marketplace-price.md` updated; this plan's three files deleted; the changelog `[NEW]` line named in its PR.

## Which steps run side by side?
- After RP1: RP2 and RP3a side by side; RP3a touches only the connector and the job. RP3b after RP3a.
- `connectors/base.php` and `trendyol.php` are entered by the marketplace plans too; check the open PRs before claiming RP1 and RP3a.
