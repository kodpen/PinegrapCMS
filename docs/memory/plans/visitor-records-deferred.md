---
name: visitor-records-deferred
description: Deferred visitor-table work: more summary dimensions (monthly partitions and early IP removal are built)
---

## What is already built?
- Retention, daily summary, chunked deletion, move to InnoDB, bots not recorded, automatic rebuild, rowless log tables in backups and stepwise restore: docs/memory/visitors/visitor-retention-and-cleanup.md
- Referring address and user agent kept once each in `visitor_texts` (step 10.6): docs/memory/visitors/visitor-texts-list.md. Left out on purpose: the other long columns (almost always empty), rewriting rows written before the step.
- Monthly partitions, old months dropped with `DROP PARTITION` (step 10.7, #499): docs/memory/visitors/visitor-monthly-partitions.md.
- IP address set to 0 after `config.visitor_ip_days` (default 30, step 10.8, #500): docs/memory/visitors/visitor-ip-early-removal.md.
- Each item below is a separate task; none is started.

## 4. More summary dimensions?
- `visitor_stats_daily` does not keep: the full referring URL, postcode, page view count, order flags, and nothing for filtered reports. Reports fall back to raw rows and say which days are missing. Add a dimension only when a real report needs it.
