---
name: visitor-channel-rule
description: The channel of a visit (paid, email, ai, organic, social, referral, direct), the search-engine table of init_tracking() and the ENUM step that appended today's engines
module: visitor_retention
---

## What is the channel rule?
- The first that matches: `paid` (tracking code carries `PAY_PER_CLICK_FLAG`, or `utm_medium` cpc, ppc, paid, paid_social), `email` (`utm_medium` email, newsletter), `ai` (host in `pg_visitor_ai_host_list()`), `organic` (`referring_search_engine` set), `social` (`utm_medium` social, or the host's last two labels in `pg_visitor_social_hosts()`), `referral` (any other host), `direct`.
- `pg_visitor_channel_sql($prefix)` in `includes/fn/visitor_retention.php` is the CASE; `pg_visitor_channel($visit, $flag)` is its pure PHP twin, the one the tests run (`tests/visitor_channel_test.php`). Change both together.
- The medium is compared lower-cased and trimmed; the host as the referrer gave it (`www.` included), so the AI list carries both forms. With no flag set only the medium makes a visit paid.

## Where is it used?
- Summary dimension `channel` (`pg_visitor_summary_dimensions()`), so the Visitors screen and `GET /reports/visitors?by=channel` read it through `pg_visitor_breakdown()`.
- The saved reports keep the old two-way split as `pay_per_click_organic` through `pg_visitor_paid_organic_sql()` (`pg_visitor_report_channel_sql()` names it).

## What about days summarized before the dimension existed?
- They hold no `channel` rows. `pg_visitor_breakdown()` asks `pg_visitor_days_without_dimension()` for summarized days with visits but no row of the dimension and reads them from the raw rows (`pg_visitor_parts_move_days()`, pure). Once the rows of such a day are removed it lists nothing for that dimension; its totals stay right. The same holds for any dimension added later.

## How are search engines recognised?
- `pg_visitor_search_engines()` in `includes/fn/seo.php`: host pattern, stored name, query key, tried in order; `pg_visitor_search_engine($host)` answers name and key, null for none. Google is `(^|\.)google\.` (every country domain), Bing any `bing.com` host. An AI assistant's host (gemini.google.com) is never a search engine.
- Appended to the `visitors.referring_search_engine` ENUM by step 11.0.163 (`upgrade_2026_11_0_search_engines()`, owner decision in `workflow/decisions-made-schema-10.md`): Yandex, DuckDuckGo, Ecosia, Brave Search, Qwant, Startpage, Baidu, Naver, Seznam. The step reads the type, appends only missing names after the last value and keeps the column's collation, so the table is not rewritten.
- Files land before the upgrade runs: `init_tracking()` stores an appended name only when `pg_visitor_search_engine_storable()` finds it in the column type (read once per request, only for those names); before that the visit is stored with no engine, as before.
- A new engine: its row in the table, its name in `pg_visitor_search_engines_added()`, an ENUM step and both install dumps.
