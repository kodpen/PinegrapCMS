---
name: customer-value-summary
description: Customer value summary (config_text customer_values): what counts, the customer key, the rebuild in the general job, the summary shape, the 200k-order measurement (#9995)
module: customer_values
---

## Which orders count, and who is the customer?
- `pg_customer_value_order_counts()`: status `complete` or `exported`, no `cancelled_at`, not refunded in full (`refunded_amount >= total` with a refund). A free order counts.
- An order with no date (`order_date` 0) is left out: the walk reads `order_date > 0` as the pass's `total` counts. Before #10667 it made a Lost customer of 1970.
- `pg_customer_value_order_amount()`: `total - refunded_amount`, never below 0, minor units.
- `pg_customer_value_order_unit()`: the base currency; only a `type = 'marketplace'` order takes its `currency_code` (a web order's code is only what the visitor saw, `refund-amounts-base-currency.md`). An order in another unit leaves the figures and is counted in `apart` per unit.
- `pg_customer_value_key()`: the billing e-mail trimmed and folded to lower case, hashed (`e` + 20 hex of md5); no e-mail: `c<contact_id>`; neither: not placed (`unplaced`). Nothing stores the key.

## How is it rebuilt?
- `job.php` calls `pg_customer_value_job_run(pg_cron_time_left(20))` on a commerce site. Due when a pass is under way or the summary is a day old (`PG_CUSTOMER_VALUE_INTERVAL`).
- The claim: config_text `customer_values_pass` (`lease`, `started`, `done`, `total`) is taken with `pg_config_text_claim()`; a live lease (budget + 60 s) makes another run do nothing. `done`/`total` are the progress the screen can show before the first summary (U6).
- The walk reads orders in `order_date, id` order on the `order_date` index, 2,000 a page, only orders placed up to the pass's start. Between runs the place and per-customer state sit in `data/temp/customer_values.state` (serialized, written then renamed); a state of another pass start is dropped and the pass restarts.
- At the end one `pg_config_text_save('customer_values', …)` replaces the summary; the state file is removed. Nothing is written on the order path.
- The pass record of an ended pass keeps `saved` and `ended`. A summary that could not be written waits `PG_CUSTOMER_VALUE_INTERVAL` from `ended` (`pg_customer_value_due()`); before #10667 it reran every tick.

## What does the summary hold?
- `pg_customer_value_summary()` reads it (null before the first rebuild). Keys: `at` (pass start), `unit`, `orders`, `customers`, `unplaced`, `apart`.
- `tiles` for `1`, `3`, `6`, `12`, `24` last whole months and `all`: customers, customers with 2+ orders in the period, orders, value, average days between orders (a gap counts in the month of the later order).
- `months` (last 36, `YYYY-MM`): first-time orders, value, returning orders, value. `cohorts` (last 24 first-order months): size, then customers ordering in each of the 12 months after.
- `cells[age][orders]`: last order within 30, 90, 180, 365 days or longer, against 1, 2-3, 4+ orders: customers and value (rows and columns: `pg_customer_value_cell_age()`, `pg_customer_value_cell_band()`; names: `customer-value-report-screen.md`, decided in `customer-value-report.md`).
- `top.all` / `top.year` (20 each): last order number, value, orders. `year` counts only the last 365 days before the pass start: its value and its orders both. No name, address or e-mail (U7).
- The state's customer text is `first,last,orders,value,recent value,number,m.m.m,recent orders`, state `v` 2; a state file of another `v` is dropped and its pass restarts.

## What did the measurement give? (#9995)
- MariaDB 10.11, PHP 8.3, 200,000 seeded orders, 61,514 customers: whole rebuild 1.0 s in one run, summary 4.1 KB, about 15 MB above loading `functions.php`; state 5.6 MB. At 500,000 orders: 2.9 s, 4.1 KB, 7.8 MB state. No table needed.

## Tests?
- `tests/customer_values_test.php`: counting rule, currencies apart, the key, runs against one run, cells, cohorts and months, no person in the summary, the page query, the job hook.
