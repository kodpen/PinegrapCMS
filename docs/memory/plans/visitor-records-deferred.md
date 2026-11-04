---
name: visitor-records-deferred
description: Deferred visitor-table work: more summary dimensions in visitor_stats_daily, added only when a real report needs one
---

## What is already built?
- Done: retention and cleanup, `visitor_texts` (10.6), monthly partitions (10.7) #499, early IP removal (10.8) #500; topic files in `visitors/`.
- Each item below is a separate task; none is started.

## 4. More summary dimensions?
- `visitor_stats_daily` does not keep: the full referring URL, postcode, page view count, order flags, and nothing for filtered reports. Reports fall back to raw rows and say which days are missing. Add a dimension only when a real report needs it.
