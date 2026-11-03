---
name: search-console-screen-messages
description: Google Search Console screen: when the tiles compare with the period before, and the messages its link, download and sitemap buttons leave
module: search_console
---

## When do the tiles show a change against the period before?
- Only when the download reaches back to the first day of that period: `pg_gsc_comparable(pg_gsc_oldest_day(), previous_from)`, the oldest `kind = 'site'` day against `pg_gsc_period()`'s `previous_from`.
- Why: linking reads the last 90 days and the daily job never reads further back (`pg_gsc_sync_range()`), so for about 90 days after linking the 90-day choice compared with a period mostly never downloaded; its missing days counted as zero and showed a large, false growth (issue #5865).
- A tile with no change shows no "against the N days before" note either.
- A low-traffic site whose first days have no impressions has no rows for them, so its oldest day is later and the comparison waits a few days longer: the safe side.

## What does a button that cannot act say?
- Link the site with an account that is no longer usable (removed in another tab, not connected for Search Console): error "The selected Google account is no longer connected for Search Console."
- Download now or Resubmit the sitemap when the site was unlinked meanwhile: error "The site is not linked to Search Console."
- Before #5867 all three reloaded the screen silently.

## Who may link, as the screen says it?
- `role <= 2`: Administrator, Designer, Manager; the text names all three with the panel's role names (Turkish "menajer"), and the Turkish setup texts add no product name (#5866).
