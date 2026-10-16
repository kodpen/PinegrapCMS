---
name: visitor-records-deferred
description: Deferred visitor-table work: early IP removal, more summary dimensions (monthly partitions are built)
---

## What is already built?
- Retention, daily summary, chunked deletion, move to InnoDB, bots not recorded, automatic rebuild, rowless log tables in backups and stepwise restore: docs/memory/visitors/visitor-retention-and-cleanup.md
- Referring address and user agent kept once each in `visitor_texts` (step 10.6): docs/memory/visitors/visitor-texts-list.md. Left out on purpose: the other long columns (almost always empty), rewriting rows written before the step.
- Monthly partitions, old months dropped with `DROP PARTITION` (step 10.7, #499): docs/memory/visitors/visitor-monthly-partitions.md.
- Each item below is a separate task; none is started.

## 3. Remove IP addresses early (KVKK)?
- After N days set `ip_address = 0`. An UPDATE over the whole table: use the same chunked, primary-key-window method as the retention delete (short transactions, pauses between chunks, `GET_LOCK`). On a table split into months a chunk can name its month (`visitors PARTITION (pYYYYMM)`).
- `idx_dedup` starts with `ip_address`; zeroed rows must not break the duplicate-visit check for current visits.

## 4. More summary dimensions?
- `visitor_stats_daily` does not keep: the full referring URL, postcode, page view count, order flags, and nothing for filtered reports. Reports fall back to raw rows and say which days are missing. Add a dimension only when a real report needs it.
