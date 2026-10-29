---
name: google-analytics-card
description: GA4 dashboard card (widget 32, #3275): purpose google_analytics, property pick on Visitor Tracking, batchRunReports cached by an hourly job, state in a sealed service entry
module: google_analytics
---

## What does it do?
- A site Google account connected for the purpose `google_analytics` (model A, scope `https://www.googleapis.com/auth/analytics.readonly`) picks one GA4 property on Settings › SEO › Visitor Tracking (`includes/settings/seo_analytics.php`, saved by `pg_ga_save_from_screen()` from `seo.save.php`). The list comes from the Admin API `accountSummaries` (`pg_ga_properties_from_api()`).
- The dashboard card "Google Analytics" (`widget_32.php`, wide) shows active users, sessions, views, key events and engagement rate for the 28 days to yesterday against the 28 before, the last 7 days of active users as bars (with a visually-hidden table), the top 5 pages by views and the top 5 sources (`sessionSourceMedium`) by sessions. Decided in #3180.

## Where is the state, and why no column?
- No schema by decision (#3180). Connection id, property id and name, `fetched_at`, `fetch_error` and the last parsed report are one sealed value: `pg_connect_service_put('google_analytics', …)` (a `connect_accounts` row with provider `service`), typed by `pg_ga_state_parse()`. Without `ENCRYPTION_KEY` nothing can be connected, so nothing is stored either.

## When is Google called?
- One `properties/<id>:batchRunReports` call (four `runReport` bodies from `pg_ga_report_requests()`: totals with two named date ranges `current`/`previous`, daily `date`, top `pagePath`, top `sessionSourceMedium`) by `pg_ga_refresh()`.
- `google_analytics_job.php` (cron entry `google_analytics_job`, hourly, light lane) reads again once the report is older than `PG_GA_TTL` (6 h, `pg_ga_due()`); choosing a property puts the job in the dispatch list (`pg_ga_job_switch_on()`) and reads at once; "Read the figures now" forces it. The card only draws the kept report.

## How are the figures shown?
- `pg_ga_card_figures()`: counts with their change as a fraction (`null` when the period before had none, no percentage drawn), the engagement rate in percent with its change in percentage points (`{var:1} points`). Signs sit outside the translated `{var:1}%`, each change carries a visually-hidden "Against the 28 days before" (`ui/dashboard-hover-figures.md`).

## Who sees it?
- Registry `show`: role < 3 or `manage_visitors`, and a property chosen (`pg_ga_linked()`); before that running sites see nothing new on the dashboard. The footer link to the settings shows only below role 3.

## What is not verified?
- Tests: `tests/google_analytics_test.php` (request bodies, parsing, rounding, empty property, cache window, property list, state). The live run needs the Google Analytics Data API and Admin API enabled in the Google Cloud project of the site's OAuth client and a GA4 property; checked on dev by the owner.
