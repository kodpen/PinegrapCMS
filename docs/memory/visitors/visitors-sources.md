---
name: visitors-sources
description: The sources section of visitors.php: channel bar and list, the eight top lists with the earlier figure, the summary days line; what it reads and what it leaves out
---

## What does the section show?
- `includes/visitors/sources.php`, `pg_visitors_section_sources()`, anchor `#sources`.
- The channel split: one stacked bar (`.pg-visitors-sources-stack`, a segment per channel as wide as its share) and a table with a matching swatch, visits, share and, with comparing on, the earlier visits and the change (`pg_visitors_change()`).
- Top lists, ten values each: referring sites, search engines, search terms, UTM source, medium and campaign, tracking codes, and affiliate codes only with `AFFILIATE_PROGRAM` on. Each row shows the earlier period's visits and the change.
- A closing line saying how many days came from the daily summary (up to which day) and how many from the visit records (`pg_visitors_sources_days_text()`).

## Where do the figures come from?
- Every list is one `pg_visitor_breakdown($dimension, $from_ts, $to_ts - 1, $limit)` per period: the period's `to_ts` is excluded, the breakdown's last moment is counted.
- The current list is read one deeper than shown, because a visit without the value (`''`) is not a source and is dropped; the earlier list is read 200 deep so a value new in the top still finds its earlier figure. Values meet without regard to case, as `pg_visitor_merge_rows()` groups them.
- The channel names come in the rule's order (`pg_visitor_channels()`); a value the rule does not know comes last; a channel with no visit in either period is left out. Colours: paid warning, e-mail info, AI danger, organic success, social primary, referral secondary, direct a pale primary ink.

## Why are search engines and their terms two lists?
- The summary keeps `referring_search_engine` and `referring_search_terms` as separate dimensions; there is no engine-and-term pair in `visitor_stats_daily`, and reading it from the raw rows would break the "summary where it covers" rule (`visitor-rollup-tables.md`).

## What does it cost?
- With comparing on, 16 or 18 breakdowns (each with its totals query). Fine for the overview's single render; a dashboard card reusing the section should read fewer lists.

## How is it tested?
- `tests/visitors_sources_test.php`: channel order and shares, unknown and earlier-only channels, empty values dropped, case-blind earlier figure, the days line, escaping of values and the bar widths, the affiliate list switch.
