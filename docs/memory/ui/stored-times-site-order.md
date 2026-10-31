---
name: stored-times-site-order
description: Subscriptions, Firewall log rows, short link state lines and the Paraşüt connection line print stored times through get_absolute_time(), not date()
---

## Which outputs print stored times through get_absolute_time()?
- `view_subscriptions.php`: next charge, started, cancelled, ended and the list column (`'type' => 'date'`, always the site's zone); event and order times (date and time, the person's zone). The pending price date (`Y-m-d`) goes through `prepare_form_data_for_output($date, 'date', false)` inside `h()`.
- `pg_waf_log_list_row()` (`includes/fn/waf_log_list.php`, Firewall log): the first hit (date and time) and the "→ last seen" line (`'type' => 'time'`), above the `get_relative_time()` of the last hit.
- `pg_short_link_state()` (`includes/fn/core.php`): "Expired on", "Opened on …; no longer works", "Valid until" (date and time). Shown under the name on the Short Links list and as the File Manager's link note; the API reads only `status`.
- `parasut_connection_line()` (`includes/fn/parasut.php`, Site Settings Paraşüt card): the connection time.
- All take `'format' => 'plain_text'` (they go into `lang()` vars or `h()`, see `panel-dates-site-order.md`).

## What was wrong before?
- They used `date('d.m.Y')`, `date('d.m.Y H:i')` or `date('Y-m-d H:i')`: the site's zone and a fixed order beside relative times and View Order dates in the person's zone and the site's order. A `month_day` site read "01.11.2026" on Subscriptions and "Nov 1 2026" on the View Order it links to.

## Which tests pin it?
- `tests/stored_times_site_zone_test.php` (short link labels; the Subscriptions source has no `date('`), `test_waf_log_list_row` (no `Y-m-d` date in the row), `test_parasut_connection_line`.
