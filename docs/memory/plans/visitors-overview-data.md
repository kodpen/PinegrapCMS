---
name: visitors-overview-data
description: Data side of plans/visitors-overview.md: which table answers which figure, the daily summary against the raw rows, the channel rule and search engines, how a Google page meets a site page
---

## Which table answers what?
- Visits and page views by hour or day: `visitor_stats_hourly`, never the raw table. Content: `visitor_content_hourly`, labelled by `pg_visitor_label_content_rows()`.
- A dimension (channel, host, search engine, landing page, country, city, device, form, UTM, tracking code) and the form and order figures: `pg_visitor_breakdown($dimension, $from, $to, $limit)` and `pg_visitor_totals($from, $to)` in `includes/fn/visitor_figures.php` (V2): `visitor_stats_daily` for the days it covers, the raw rows for the rest, the two lists added up by value without regard to case, as `api_reports_visitors()` does today; the answer names `summary_days` and `raw_days`. The summary's rest row counts in the totals only.
- People on the site now: `visitors.stop_timestamp` in the last 20 minutes (the dashboard's rule).
- Google: `pg_gsc_totals()`, `pg_gsc_daily()`, `pg_gsc_top()` over `seo_search_daily`; a Google period ends on the latest day with figures (`pg_gsc_latest_day()`), said on the screen.

## Which days come from where?
- `pg_visitor_summary_range()` (V2): first and last day of the contiguous run of summarized days (a day counts when its totals row, `dimension = ''`, is there) ending at the most recent one. `pg_visitor_day_sources($from, $to)` (V2): the window cut into `summary` and `raw` ranges: raw for today, for days after the run and for days before it the backfill has not reached.
- `pg_visitor_rows_from()` (V3, `visitor_retention.php`): the first moment the raw rows are complete: the retention cutoff, 0 with retention off. The saved-report screen reads the summary only before this moment, since it filters and lists single visits; the overview and the API read it wherever it covers.
- The job (V3): with `config.visitor_summary` on, every run summarizes yesterday first, then fills backwards from the oldest covered day within its budget, whatever `visitor_retention_days` is; the last two days are written again on each run (`pg_visitor_summarize_day()` deletes a day before writing it), so an order completed after midnight on a visit begun before it is counted. Removal is unchanged: only summarized days past the cutoff.

## What is the channel rule?
- The first that matches, in this order: `paid` (tracking code carries `PAY_PER_CLICK_FLAG`, or `utm_medium` in cpc, ppc, paid, paid_social), `email` (`utm_medium` email, newsletter), `ai` (host in the assistant list: chatgpt.com, perplexity.ai, gemini.google.com, copilot.microsoft.com, claude.ai, you.com), `organic` (`referring_search_engine` set), `social` (`utm_medium` social, or the host in the social list: facebook, instagram, x.com, twitter, linkedin, youtube, tiktok, pinterest, reddit, whatsapp, telegram), `referral` (another host), `direct`. One `pg_visitor_channel_sql()` CASE and a summary dimension `channel` (V4); `pay_per_click_organic` stays for the saved reports. The host lists are arrays in `visitor_retention.php`, matched on the last two labels.
- Search engines (V4): `init_tracking()` reads a table of host pattern, name and query key through a pure `pg_visitor_search_engine($host)`; Google matches `(^|\.)google\.` on the host (google.com.tr was a referral). Added, and appended to the ENUM: Yandex, DuckDuckGo, Ecosia, Brave Search, Qwant, Startpage, Baidu, Naver, Seznam (`workflow/decisions-made-schema-8.md`).

## How is a Google page matched to a site page?
- `pg_visitor_page_key($address)` (V10): the path under the site root (`pg_gsc_page_label()` for a Google address), without scheme, host, query, fragment, a trailing slash or `.html`, decoded and lower-cased; `''`, `/` and every home page's name are one key (what `pg_home_page_group_expression()` does in SQL). Landing pages and content rows (`page_name`, pretty paths) go through the same function. Pure, tested with Turkish letters and encoded paths.
