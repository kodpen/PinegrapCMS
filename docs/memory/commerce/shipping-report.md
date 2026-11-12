---
name: shipping-report
description: Shipping report (view_shipping_report.php): which orders it counts, how its period is read and kept, the longest period, the figures and line over its table
---

## Which orders does it count?
- Lines of `complete` or `exported` orders only, the set its heading links open in the order list (`status=complete_or_exported`). Before #5716 it excluded only `incomplete`, so cancelled orders' lines were counted as still to ship and the linked list did not show them.

## How is the period read?
- `pg_shipping_report_period()` (`includes/fn/shipping.php`) takes the address's `start_date`/`end_date` as a pair; a date left out comes from the session. An impossible date or an end before the start leaves the session's period as it was; a broken stored period, or none, gives two weeks from today. Only the result is written to the session (#5724: an inverted period was drawn with no day columns, the arrows walked backwards, and it stayed in the session).
- A period longer than `PG_SHIPPING_REPORT_MAX_DAYS` (93, one column a day) is cut at that length.

## What does the screen draw over the table?
- The shared view (`ui/report-view.md`, #8811): figures from the Grand Total row by `pg_report_view_shipping_figures()` (Products, Before, In this period: the days summed, After, Total; units of the status chosen), then "Units by ship date", a line of one point per day of the period as the day columns count them (`pg_report_view_shipping_chart()`; none for a one-day period or with no unit in it).
- The status select and the period arrows stay in the toolbar: the screen has no saved settings, so no settings drawer and no ready report. Query, period rule and the heading links are untouched.

## What was not verified?
- The screen ran on no sandbox; the period rule is pinned in `tests/shipping_report_period_test.php`, the status filter by a source check there.
