---
name: dashboard-list-links
description: Dashboard greeting and card links open their list on the period and filters they counted; dashboard day windows step calendar days
---

## How does a greeting or card link open the rows it counted?
- Through `pg_dashboard_list_url($screen, $query, $start, $stop)` (`includes/dashboard/widgets.php`, required by `brief.php` too): the list's filters plus the counted period as `start_`/`stop_` month, day and year, which `view_orders.php`, `view_contacts.php` and `view_submitted_forms.php` copy into their session like `pg_sales_map_orders_url()`. A bare link kept whatever range, group or form an earlier visit left in the session and could hide counted rows.
- New contacts this week: the week, `advanced_filters=false`, `contact_group=[All]` (translated, as the list compares it).
- Forms this week: the week, `advanced_filters=false`, `custom_form=[All]`, `query=` empty. The list's hidden date type stays (default "last modified"): a form submitted this week was also last modified this week, so every counted one is on it.
- Refunds (greeting and Operations Desk "N more waiting"): `reset=true&status=refund_pending&type=any` from the oldest waiting order's `order_date`; the count is all time and all types, the list's own default is a month of online orders.
- Pending shipments: `reset=true&advanced_filters=true&shipping_status=unshipped` over the same 30 days the greeting counted.
- Left as they are: the baskets link (its list is only wider) and the welcome card.

## How are dashboard day windows stepped?
- `pg_dashboard_days_back($midnight, $days)` = `strtotime('-N days', $midnight)`, never `$midnight - N * 86400`: across a clock change a day is 23 or 25 hours, so the window started at 23:00 the day before or 01:00. Visitor Summaries (`widget_5.php`) steps "yesterday" and its seven chart dates with `strtotime()` too; the day after the spring change its "Yesterday" chart plotted Saturday.
- `brief.php`'s `$greeting_week_start` and `widget_3.php`'s `$from_week` stay the same expression (`dashboard-card-figures.md`).

Test: `tests/dashboard_test.php`.
