---
name: visitors-google
description: The Google search section of visitors.php: its window ending on the latest day with figures, tiles, top lists, the two opportunity rules, the unlinked card
---

## What does the section show?
- `pg_visitors_section_google()` (V9, #6472): tiles for clicks, impressions, click rate and average position (lower is better) with `pg_visitors_change()`, a note with the dates read and the dates compared, clicks and impressions by day (impressions dashed on the right axis), top searches and top pages (10 each, `pg_gsc_top()`), and two opportunity tables. Read only from `seo_search_daily` through `pg_gsc_*` (`includes/fn/search_console.php`, required by the file).
- A page links to its address (label `pg_gsc_page_label()`); a search links to Search Console's performance report filtered on it (`pg_visitors_google_query_url()`: `resource_id`, `query=*<query>` meaning "contains", `start_date`/`end_date` as Ymd).

## Which days does it read?
- `pg_visitors_google_window($period, $latest)`: as many days as the overview period, ending on the period's end or on `pg_gsc_latest_day()` when that comes first (`early`; the note then says Google publishes two or three days late). Today alone reads the latest day alone. The previous window is the same length just before (`pg_gsc_period()`).
- The previous window is read only with comparing on and when the download reaches its first day (`pg_gsc_comparable()`); otherwise the tiles say "No earlier figure" and the note says the days before were not downloaded, never a growth from zero.

## What counts as an opportunity?
- From the top 500 searches by clicks of the window (`PG_VISITORS_GOOGLE_POOL`): "Shown often, rarely clicked": impressions above the median of that list and a click rate under half the site's (none when the site has no clicks); "Close to the first page": average position 8 to 20 with impressions. Both most impressions first, 10 rows. Pure: `pg_visitors_google_opportunities()`, `pg_visitors_google_median()`.

## What when there is nothing?
- Not linked (`pg_gsc_linked()` false, also before the schema): a small card with one line and "Set up Search Console" to `search_console.php` (decision 6), never an empty or hidden section.
- Linked without any figure: "No figures yet…"; a window without impressions: "Nothing in this period.".

## What pins it?
- `tests/visitors_google_test.php`: the window (early end, a past period, today), the median, both rules, the link's encoding, escaping of a search. CSS in the `Section: google` block of `pg_visitors.src.css`.
