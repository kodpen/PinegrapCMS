---
name: visitors-conversions
description: The conversions section of the Visitors overview (includes/visitors/conversions.php): which tiles, the conversion rate, the funnel, the chart by grain, commerce off, the empty state
---

## What does the section show?
- Tiles: forms submitted; with `ECOMMERCE` also orders created, checked out, completed, revenue (`order_total`, minor units, shown with `pg_money_text(/100, BASE_CURRENCY_SYMBOL)`) and the conversion rate. Each against the earlier period through `pg_visitors_change()` when comparing is on.
- Conversion rate = completed orders over visits, in percent, two decimals; no visit gives `–` and "No earlier figure", never 0 %.
- With commerce on: the order funnel (visits, created, checked out, completed, each with its share of the visits) as `pg_visitors_bars()` beside the chart.
- With commerce off: forms alone, the chart has the forms line only, no funnel.

## Where do the figures come from?
- `pg_visitor_totals()` for the period (`from_ts` to `to_ts - 1`) and for the earlier one (`previous_*`, cut at the same hour when the period ends today).
- The chart: one bucket per step of the period's grain (day, Monday week, month; `pg_visitors_conversions_buckets()`, pure), cut to the period. The summary run is read once (`pg_visitor_summary_range()`) and each bucket split against it with `pg_visitor_day_split()` + `pg_visitor_totals_of_parts()`: at most two queries a bucket. A one-day period has no chart. Forms and completed orders share one axis; each bucket's revenue is a tooltip line (`notes`), not a second axis.

## When is it empty?
- When neither a form nor (with commerce on) an order figure is above 0: `pg_visitors_empty()`. Visits alone belong to the traffic section.
