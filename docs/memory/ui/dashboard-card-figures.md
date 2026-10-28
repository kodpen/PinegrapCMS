---
name: dashboard-card-figures
description: Dashboard card figures: the brief's "this week", visitor "Last 12 Months", open-ended offers, currency rates, Activity job rows, job summaries kept out of the site log
---

## What is "this week" on the dashboard?
- Today and the six days before it, from midnight: the E-Commerce Summary card (`widget_3.php`, `$from_week`) and the brief (`includes/dashboard/brief.php`, `$greeting_week_start`) both use it, for orders, baskets, the sales-map line (week, then the three weeks before it), new contacts and forms. The brief once used the last 7 × 24 hours, so its "İzmir leads with 10 orders this week" sat beside a card saying 7 (#2049).
- "Today" in the brief is still the last 24 hours against the 24 before them.

## What does Visitor Summaries' "Last 12 Months" count?
- The eleven months before this one and this month so far (`widget_5.php`), so it is never below "This Month". Before, it summed only the twelve finished months and read 0 beside "This Month 61" on a new site (#2048).

## How does the Offers card show an offer with no end?
- An open-ended offer's `end_date` is `PG_OFFER_OPEN_END_DATE` (2099-12-31, `edit_offer_f.php`); `_pg_offer_days_left()` gives null for it and the card prints "Open-ended" (the list's word) instead of 26728 days (#2047).
- Current offers (enabled, started, not ended) and the last 20 expired ones are two queries: one list ordered by end date let 60 expired offers push every open-ended one out. Expired end dates print in the site's date format.

## Small rules on other cards
- Currency rates (`widget_16.php`): the rate is `pg_format_number(..., 5)` like the "1 $ = …" line; the edit link carries no `send_to` (the card is drawn by `api.php`, and `edit_currency.php` returns to the currency list anyway).
- Activity (`widget_7.php`): `pg_dashboard_log_actor()` names a row with no user or the literal `UNKNOWN` and no address "Scheduled job", with an address "Unknown"; `pg_dashboard_log_meta()` adds "IP …" only when there is one (#2049).
- Live engagement (`widget_4.php`): the labels take a `{suffix:1}` plural ("1 Active Visitor"); Turkish has one form ("Aktif Ziyaretçi").
- Calendars (`get_calendar()` minimal mode): short day names (`Sun`…`Sat` keys) in `<abbr>` with the full name, since the card cut both Friday and Saturday to "Cu…".

## Why do job scripts not print their summary under the general job?
- The `*_job.php` scripts print a one-line summary ("sent: 0, not sent: 0") only when started by hand on the command line: `(php_sapi_name() === 'cli') && !defined('PG_CRON_DISPATCH')`. Pulled in by `job.php`'s dispatcher, any printed text goes to the site log through `pg_cron_dispatch_output()` (once a day per job), which filled the Activity card with English "printed a message" rows that did no work (#2049). A job that has something to report logs it itself with `log_activity()`.

Test: `tests/dashboard_test.php`.
