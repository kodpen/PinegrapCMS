---
name: visitors-alerts
description: The alerts section of the Visitors overview (includes/visitors/alerts.php): the six rules, their thresholds and minimums, the Google window, the quiet-day check, drawn only when something fell
---

## What does the section show?
- One line per alert, drawn first on `visitors.php`: an icon, the figures (now against the period before) and a link to the section that shows them, the period kept in the link. Nothing at all (`''`) when no rule fires.
- Rules, each a pure function over figure arrays (`tests/visitors_alerts_test.php`):
  - visits down by more than 30 % (`PG_VISITORS_ALERTS_DROP`), at least 50 visits before → `#traffic`;
  - a landing page that lost more than half its visits, at least 50 before; names folded by case, `''` and `/` one home page; the three largest losses → `#content`;
  - with `ECOMMERCE`, the conversion rate (completed orders over visits) under half of before, at least 5 orders and 50 visits before → `#conversions`;
  - Google clicks down by more than 30 %, at least 50 before → `#google`;
  - a page that lost more than half its Google clicks, at least 20 before; three at most → `#pages` when that section exists, else `#google`;
  - no visit recorded today from 10:00 (site clock) although yesterday had visits: tracking may be broken → `#traffic`, listed first, checked on today whatever the period.
- Thresholds and minimums are constants at the top of the file. The minimums keep a quiet site from being warned about three visits against five.

## Where do the figures come from?
- `pg_visitor_totals()` and `pg_visitor_breakdown('landing_page_name', …, 200)` for the period and the one before (`previous_*_ts`, cut at the same hour when the period ends today), computed whether comparing is on or off.
- Google: the window the Google section uses (the period's length, ending on `pg_gsc_latest_day()` when that comes first, `pg_gsc_period()`), `pg_gsc_totals()` and `pg_gsc_top('page', …, 200)`; no Google alert when not linked or when `pg_gsc_comparable()` says the window before was not downloaded.
- No table, no job: worked out on every render (plan decision 7). The section reads the modules, never another section's file.
