---
name: order-charged-currency
description: orders.charged_currency and charged_rate (11.0.283): an order kept in the currency it is charged in, its rate, the base-unit sums, the marketplace fill, the money function
module: order_currency
---

## What do the two columns hold?
- `orders.charged_currency` CHAR(3): the currency every stored amount of the order is in; `''` means the base currency. Every web order is `''` until markets charge another currency.
- `orders.charged_rate` DECIMAL(18,8): base units one charged unit was worth on the order's day; 0 for a base order and when no rate was known.
- No second total is stored: `total`, `subtotal`, `tax` and the lines stay as charged. Added by `upgrade_2026_11_0_order_charged_currency()` (11.0.283), PR #12105.
- `orders.currency_code` is still what the visitor was shown on a web order; never read it as the charged currency.

## How does a sum read base units?
- `pg_order_base_sql('orders.total')` (alias as 2nd argument): the expression as it is for `''` or the base code, `ROUND(expr * charged_rate)` for another, 0 when the rate is 0. Wrap the whole per-order expression: `SUM(pg_order_base_sql('orders.total - orders.tax'))`.
- Without the columns (files before the upgrade, the DB-less tests) it returns the expression unchanged.
- One amount in PHP: `pg_order_base_amount($minor, $order)`, the same rule with `round()`.
- Converted so far: dashboard Shop card and widget 3, the sales map, the ERP profit report (uninvoiced orders and their lines), the audience rule "order total". Line sums of the profit report may differ by a cent from the header: each is rounded alone.

## Why does an order with no known rate count 0?
- Its figure is in another unit; adding it as base was the bug the census found (a 100 USD marketplace order counted as 100 of the base). Leaving it out matches `pg_product_insights_base_unit_sql()`.

## Where does the rate come from?
- `pg_order_charged_for($code, $order_date)`: the `currency_rates` row in force on the order's day (`pg_currency_rate()`), else today's (`1 / currencies.exchange_rate`, else the latest dated row), else 0. Returns null while the columns are missing.
- A marketplace order (`mp_order_write()`, `includes/api/outbound/orders.php`) is written with both columns from its package currency and `placed_at`.

## How do older marketplace orders get them?
- `pg_order_currency_fill_run()` from the general job (`job.php`, `PG_ORDER_CURRENCY_FILL_SECONDS` = 5 of a tick): orders read by id, 1,000 a query; a marketplace order with `charged_currency = ''` and a non-base `currency_code` gets the pair of its day. The place is `config_text` `order_currency_fill` (`{"after":id}`, then `{"done":1}`); delete the row to run it again, which changes nothing that is filled.
- Until it is through, an older order still counts as base in the sums.

## How is an order's amount printed?
- `pg_order_money($order, $minor)`: plain text in the order's own currency (the base symbol, the currency's symbol, else ` CODE` after the figure), never converted; the caller escapes it. Which screens use what: `order-currency-screens.md`.
