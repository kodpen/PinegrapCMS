---
name: bug-hunt-areas-customer-value
description: Bug hunt of the customer value summary counts (agent channel message 10367, #10667): what was read, the four faults fixed, what was found clean
module: customer_values
---

## What was the area, and what was read?
- Architect, agent channel message 10367: the counting of the customer value summary (the Customers Report's figures). Hunt PR #10667.
- Read: `includes/fn/customer_values.php`, the rebuild hook in `job.php`, `view_customer_report.php` (CSV, group from a cell, figures only), `api_reports_customer_value()` and its presenter in `includes/api/resources/reports.php`, the refund and cancel writes (`returns.php`, `store_credit_refunds.php`, `process_order_cancellation()`), the audience rules a cell hands over, `tests/customer_values_test.php` and `customer_values_groups_test.php`.

## What was fixed?
- A top customer's name came from any order with that number; a number shared by two orders (no unique index) could show another buyer's name. Now only counted orders are read and a shared number gives no name (`customer-value-groups-csv-api.md`).
- An order with no date was counted as a customer lost since 1970 and made their real first order a returning one; the progress read more orders than its total. Now left out (`customer-value-summary.md`).
- The API's cohorts answered 0 for months not begun yet; now only the months that have begun.
- A summary that could not be written made the whole rebuild run on every tick; now it waits a day.

## What was found clean?
- The counting rule: a return's refund grows `refunded_amount` by the payment part only, and `orders.total` excludes the gift card part, so a full return still leaves the order out; a restored order keeps a refund that went through.
- Currencies: a web order is in the base currency whatever its `currency_code`; marketplace codes are folded to upper case.
- Keys: the e-mail is folded and trimmed, so a guest who opens an account later with the same address stays one customer (decided: the billing e-mail, not the account).
- Month, cohort and age edges use the site's time zone in the job and the screen alike (`init.php`); a 30-day-old order sits in the first row.
- The rebuild: keyset paging on `order_date, id`, a killed run resumes from the last renamed state, the lease and compare-and-swap claim keep two runs apart, and a late run of the same pass writes the same summary.
- Names: the screen, CSV and Excel ask for the contacts right before a name is read; the API asks for `customers:read`; CSV names go through `csv_cell()`.

## Which issues were opened?
- None.
