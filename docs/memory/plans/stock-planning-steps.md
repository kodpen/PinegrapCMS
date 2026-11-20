---
name: stock-planning-steps
description: Plan stock-planning, steps SP1 to SP5: the daily record first (its history starts when it lands), the forecast, the screen and supplier order, the bell and API, close-out
---

## Which steps are there?
- SP1 (M) first, soon: the history starts the night it lands. Done: #10612.
  - Migration step in 2026.11.0, idempotent helpers: `erp_stock_days` (`product_id` INT UNSIGNED, `day` DATE, `sold` INT UNSIGNED NOT NULL DEFAULT 0, `stock` INT UNSIGNED NULL for unknown; PRIMARY (`product_id`, `day`), KEY `idx_day` for the pruning).
  - New: `includes/erp/stock_plan.php` (a day's units per product from the four sources and components; the night pass), `erp_stock_plan_job.php` (a `pg_cron_jobs()` entry, `pg_cron_ran()`, in `JOB_DISPATCH`; resumable with `pg_cron_time_left()`), `tests/erp_stock_plan_test.php`.
  - Tests, red first: each source counted; carts and cancelled not; an order's own ERP invoice not counted twice; a bundle as its components; the day by the site's time zone; sparse rows; the 14-day recount keeping past stock; the 420-day fill once; nothing with Accounting off. Features: P1, P2.
- SP2 (M) after SP1: the forecast. Done: #10654.
  - Migration step: `erp_stock_plan` (PRIMARY `product_id`, a cache rebuilt every night: units a day, season, supplier, lead time and its source, days left, run-out and order-by days, quantity, state, last sale, notice stamp; the types in the PR body, a column outside this list is asked) and `erp_accounts.lead_days` SMALLINT UNSIGNED NOT NULL DEFAULT 0 (0 = not typed), saved by `erp_account_save()`'s whole-row rule.
  - `includes/erp/stock_plan.php`: pure functions for P3 to P10; the night pass fills the plan after the day's row. Open quantities through `erp_purchase_order_quantities()`.
  - Tests: out-of-stock days skipped; season capped and left out on thin sales; lead time median; supplier from invoice, then order; open orders taken off; dead stock; not enough history. Features: P3 to P10.
- SP3 (M) after SP2: the screen.
  - New `erp_stock_plan.php` (`validate_erp_access()`, `pg_screen_toolbar()`), its Accounting menu entry and settings card; the account card's Lead time field.
  - `add_erp_purchase_order.php` and `erp_purchase_order_rows_for_products()` take a quantity per product by POST, which only fills the form; the minimum stock screen keeps its GET `products[]`.
  - No pictures (`workflow/look-pictures-none.md`). Features: P11, P12.
- SP4 (S) after SP2: the bell and the API.
  - `includes/erp/alerts.php` (`erp_alert_low_stock()`), `includes/erp/api.php` (route, `erp_openapi_objects()`), an `includes/erp/api_*.php` reader; `php tools/check_api_schema.php`. Features: P13, P14.
- SP5 (S) close-out: every P number checked against the code with its PR; `erp/stock-planning.md` written; `erp/erp-alerts.md` updated; this plan's three files deleted; the changelog `[NEW]` line named in its PR.

## Which steps run side by side?
- SP3 beside SP4 after SP2. SP1 and SP2 one after the other.
