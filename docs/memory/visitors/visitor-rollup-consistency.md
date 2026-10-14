---
name: visitor-rollup-consistency
description: Keeping the two visitor summary tables consistent: both must SUM(page_views), running-total race with backfill, and rebuilding derived data (2026.3.8).
---

## Must both summary tables count the same thing?
- Yes. `visitor_stats_hourly.page_views` was filled with `SUM(visitors.page_views)` but `visitor_content_hourly.views` with `COUNT(*)` (sessions). The tooltip took the hour total from one and the top item from the other, so it showed e.g. "Sayfa Görünümleri: 29" with "home-1 - 1".
- Both must use `SUM(page_views)`.
- In backfill all of a session's views are written to its ENTRY page. That inflates the entry page, but the old row has no other page record; the alternative is dropping the data.

## Why is an id cutoff not enough (`visitors.page_views` is a running total)?
- Backfill reads each session's `page_views` at the moment it reaches that row. If a session below the cutoff is still browsing, the same views are counted by the live counter AND by backfill's last read (25 views appeared as 29).
- `pg_visitor_view_awaits_backfill()`: if the visitor's id is AHEAD of the cursor and below the ceiling, the live count is skipped; that row is backfill's job. Once the cursor has passed the row backfill never looks again, so counting live is correct and loses nothing.

## What if the summary tables get corrupted?
- They are derived data and are regenerated. A bucket holds one total with no record of where each half came from, so it cannot be fixed in place. Migration `2026.3.8` `TRUNCATE`s both tables and rebuilds from `visitors`.
- Cost: the item breakdown collected live since the upgrade drops to page level (`visitors` never knew the item).

Related: docs/memory/visitors/visitor-rollup-backfill-runtime.md
