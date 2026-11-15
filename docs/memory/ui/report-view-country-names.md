---
name: report-view-country-names
description: The report screens name a country level's places by the site's countries list (#9718): where, which levels, what keeps the stored code
module: report_view
---

## Where does a report show a country's name?
- On a country level (the order report's Billing Country, the visitor report's Country) the table's group rows and the chart's rows and map tips name the place by the site's countries list, in place of a stored code (TR shows the list's name for TR). The architect's look, channel 25 message 4223 (RS8b, #9718).

## How?
- `pg_report_view_country_name($level, $value, $names)` (pure): only for `billing_country`, `shipping_country`, `country`; the code is matched trimmed and in upper case; a stored name (Turkey) or an unknown code stays as stored.
- `pg_report_view_country_names()`: `countries.code => countries.name`, read once a request. The screens wrap their `update_summarize_by_name()` calls for the table and the chart with it.

## What keeps the stored value?
- The map's place codes (`pg_sales_map_country_code()` on the stored text), grouping and sorting (the query groups the stored text), and the visitor report's CSV export, which calls `update_summarize_by_name()` alone.

Tests: `tests/report_view_test.php`.
