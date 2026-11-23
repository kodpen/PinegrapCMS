---
name: orders-list-figure-strip
description: All Orders (view_orders.php) one bar with the short period, the figure strip with the change against the period before, and Summary: closed until opened, read when it opens, kept per person
module: order_list
---

## How is the bar put together?
- One bar (`#button_bar`): Add Order, Export, Import Tracking Numbers, a rule (`.pg-orders-bar-rule`), the period navigator (`pg_period_nav()` with ` pg-orders-period`), then one group pushed right (`.pg-orders-bar-end`, no grow slot): Sort, the status select, the type select, the profit select when profit is on, the filters button.
- Under 1280 px the rule becomes a line break, so the navigator starts the second row with the group after it; nothing is moved with `order`, so Tab follows the rows (`admin-screen-toolbar-rules.md`).
- The period reads short (`pg_list_period_heading($range, null, true)`: "22 Eki – 22 Kas 2026", the year once inside one year) and the status select is at most 200 px, its text cut with an ellipsis, so in Turkish the bar is one row at 1440 px (#11208; it needed about 1,447 px of 1,348).
- On a phone (under 576 px) Sort, the status select, the type select and the filters button share one row, the status select taking the free width: four rows. Sort keeps its markup place (no `order`), so it is not on the Import Tracking Numbers row the fix list named.

## What is the figure strip?
- Today's four figures (Total Sales Amount Online, Total Sales Amount and Orders of the current filter, Refund Pending) as cells of one card, `.pg-figure-strip` (`ui/look-figure-cards.md`), each sparkline at its value's right. Refund Pending above zero is `.is-bad`.
- The board drew no sparkline in the strip; the plan's words keep them, and they were kept (OL1).
- The two filtered figures carry their change against the period before, of the same length (`pg_order_list_previous_period()`, `pg_order_list_change_html()`): "+12%" teal, "−8%" rose, "0%" plain, nothing when the period before had none. One query over the list's own filters with the period swapped (`$orders_previous_sql`).

## How does Summary open?
- The button at the strip's end (`#pg_orders_summary_toggle`) opens a row under the strip with Top 5 products and Payment methods as bars in markup: name, bar, figure as text (`pg_order_list_summary_html()` in `includes/fn/order_list.php`).
- Closed until opened. Its markup is read the first time it opens, from `view_orders.php?summary=1`, over the list's own filters (`$orders_filtered_sql`); the page itself no longer runs those two queries.
- Open or closed is the person's, kept in the browser as the list's columns are: localStorage `pg.orders.<user id>.summary` = `1` when open. Storage that throws: the choice holds for the page. A failed read shows "The summary could not be loaded." and the next opening tries again.

## Where is it pinned?
- `tests/order_list_summary_test.php`, the change figures in `tests/order_list_quick_test.php`; the old pins of the cards and charts were rewritten in `look_figure_cards_test.php`, `chart_text_equivalents_test.php`, `status_colour_words_test.php`, `panel_chart_colours_test.php`.

Related: docs/memory/commerce/orders-list-quick-filters.md, docs/memory/ui/look-figure-cards.md
