---
name: erp-stock-planning
description: Stock Planning (Accounting › Stock Planning): what it is, the owner's and architect's decisions, its parts and which file holds each, the defaults, what is left out; PRs #10612, #10654, #10693, #10687
---

## What is Stock Planning?
- A nightly plan per stock-tracked product from its sales speed: days left, run-out day, order-by day, the quantity to order net of open purchase orders, a state, dead stock at average cost. Seen on Accounting › Stock Planning, in the low stock bell and through `GET /erp/stock-plan`; one press per supplier opens New purchase order filled in.
- Built in 2026.11.0: #10612 (daily record), #10654 (forecast), #10693 (screen, supplier order), #10687 (bell, API).

## What did the owner decide?
- Owner (2026-11-20): "Evet, plana girsin" (agent channel poll 163, message 10064): speed from every channel without out-of-stock days, last year's weeks for the season, the supplier's measured lead time, dead stock after 90 days at average cost, a press per supplier, the bell, API and MCP. The question: `workflow/decisions-made-product-80.md`.

## What did the architect decide?
- Architect (2026-11-20): settings in Accounting, each changeable by the owner: safety 7 days, cover 30 days (the architect's), dead stock 90 days (the poll's); values in `erp-stock-plan-forecast.md` (agent channel message 10139).
- Architect (2026-11-20): `erp_stock_days` written sparse, `erp_stock_plan` a cache rebuilt every night, `erp_accounts.lead_days`; a new column outside these is asked first (`workflow/decisions-made-schema-30.md`).
- Architect (2026-11-20): no product-to-supplier link table; the supplier is read from purchase invoices and orders.
- Architect (2026-11-20): the press sends the quantities by POST, which only fills the form; the minimum stock screen keeps its GET `products[]`.
- Architect (2026-11-20): with the Accounting module off nothing is read or written.

## What binds every change to it?
- Nothing is ordered for the merchant: nothing is saved or sent until a person saves the purchase order.
- The plan only reads stock and sales; it never changes a count. A bundle is never planned: its components carry its sales.
- Out-of-stock days are known only from the record: a night the job did not run has no stock for that day.

## Which file holds what?
- Daily record, sources, night pass, recount, 420-day fill, marks: `erp-stock-days.md`.
- Speed, season, supplier, lead time, quantity, states, dead stock, the nightly rebuild, `lead_days` saving: `erp-stock-plan-forecast.md`.
- The screen, its filters and columns, the settings card, New purchase order per supplier, the account card's Lead time: `erp-stock-plan-screen.md`.
- The low stock bell for a planned product and the API and MCP tool: `erp-stock-plan-bell-api.md`; the bell in general: `erp-alerts.md`.
- Code: `includes/erp/stock_plan.php`, `erp_stock_plan_job.php`, `erp_stock_plan.php`, `includes/erp/alerts.php`, `includes/erp/api_stock_plan.php`.

## Where does the record differ from the plan's wording?
- The day the record began is a `cron_runs` mark (`erp_stock_plan_began`), not a config setting: the config table takes no new column (`data/config-table-no-varchar.md`).

## What is left out?
- Sending an order by itself, supplier price lists and pack sizes, a plan per stock location, untracked products, a learned model. Each needs the owner's yes.

## What was not verified?
- No database and no browser run in the sessions that built it: the migration steps' double run, the night job on real orders, the screen and the filled order form were not opened (`workflow/look-pictures-none.md`).

Tests: `tests/erp_stock_plan_test.php`, `erp_stock_plan_forecast_test.php`, `erp_stock_plan_screen_test.php`, `erp_stock_plan_bell_test.php`.

Related: docs/memory/erp/erp-stock-days.md, docs/memory/erp/erp-stock-plan-forecast.md, docs/memory/erp/erp-stock-plan-screen.md, docs/memory/erp/erp-stock-plan-bell-api.md
