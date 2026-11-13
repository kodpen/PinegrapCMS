---
name: erp-profit-report
description: ERP profit and loss (includes/erp/profit.php): what counts as revenue and cost, the optional uninvoiced orders, the negative margin text, and where a new cost or revenue source goes.
---

## Where is the profit and loss built?
- `includes/erp/profit.php`, `erp_profit_report($from, $to, $options)`; screen `erp_profit.php` (`?export=xlsx` writes `erp_profit_workbook()`), the month card of `erp_dashboard.php`, and the API (`api_reports.php`, `orders` parameter). Period at most two years, years 1970-2100 only (`erp_profit_period()`, `ERP_PROFIT_FIRST_YEAR`/`LAST_YEAR`); outside them this month with "Please enter a valid date.". The VAT report uses the same period.
- Two dates given the other way round are swapped, then capped at two years with the same notice; before #9078 a reversed pair skipped the cap (1970-2100 in one report).
- A management view, not the accountant's books: base currency, without VAT. It is not an official income statement (no depreciation, no accruals).

## What counts?
- Sales: the line base (line total less discount) of issued sales invoices of the period, less the returns issued in it; `draft` and `cancelled` are left out.
- Cost of goods: from the stock moves of each line sold or taken back (`includes/erp/stock.php`). A line with a product but no move (invoiced before stock was kept), or a move of cost 0 (`erp_stock_record()` writes 0 when the product had no `erp_product_costs` row yet), is costed at today's average cost and counted as estimated; with no average either it counts in `uncosted_lines` ("no cost known", the overstated-profit note, the workbook row) like an order line (#5702). A line without a product (service, shipping) has none.
- Purchase invoice lines without a product are costs (a service bought on an invoice), less what went back.
- Expenses at their cost: net plus the VAT that is not reclaimed (`total_base - tax_base` when `tax_deductible`).
- A new cost or revenue source goes into `erp_profit_report()` AND its workbook `erp_profit_workbook()`, or the screen and the Excel disagree.

## How are uninvoiced orders counted?
- Optional revenue rows: `$options['orders']`. The screen keeps the choice a year in the cookie `pg_erp_profit_orders` (`erp_profit_orders_choice()`); the dashboard card reads the same choice.
- Only billable orders with `orders.erp_invoice_id = 0`, by order date (`erp_profit_orders_where()`), costed at today's average: an invoiced order is never counted twice; cancelling its invoice empties the link and the order counts again.

## How is a negative margin written?
- With `erp_profit_margin_text()`: "-%12,5". `erp_percent_text()` puts the percent sign first in Turkish, so a negative number passed to it reads "%-12,5". No revenue gives a dash.

## Why do the top cards stack up to 1536 px?
- "Income statement" and "By month" sit side by side only from `2xl` (`2xl:col-5` / `2xl:col-7`, like the row below them). With `xl:` the 13-month table (760 px) overflowed its 644 px card at 1280 px and scrolled inside it (#4529, from #4440).

## How are the months and the picker built?
- `erp_profit_months($from, $to)` counts year × 12 + month; never compare `Y-m` strings: after `9999-12` `date()` gives `10000-01`, which sorts first, and the loop ran until the 300 s fatal (#5700).
- `erp_profit_period_options()` (from `erp_profit_period_choices()`) builds the month/quarter/year lists of both screens: last 24 / 8 / 6, plus the shown period when older, selected, so a submit or the orders switch keeps it (#5704).
