---
name: waf-log-aggregation-and-cap
description: How waf_log aggregates by sha1 key + 5 min bucket, why the query string is excluded, waf_enforce_log_cap, use SUM(hit_count) (2026.2.6).
---

## Why does `waf_log` aggregate?
- One row per event let a single test burst write 6,500 rows; in a sustained attack the log became a bigger load than the attack itself (the firewall chokes its own DB).
- Key: `sha1(ip | rule_id | action | category | path)` + 5-minute bucket (`WAF_LOG_WINDOW`). Written as one `INSERT ... ON DUPLICATE KEY UPDATE`; on a repeat event the table does not grow, only `hit_count` rises.
- The query string is deliberately NOT in the key: the payload changes every request, so during fuzzing (when aggregation matters most) it would never aggregate. The payload stays as a sample in `matched`.
- On a repeat the sample columns (`matched`, `request_url`, `user_agent`) are NOT rewritten: the first sample is enough, updating five string columns on every blocked request brings the cost back.
- Rows: single-IP burst 6,500 requests = 1 row; fuzzing 3,000 payloads = 1 row; distributed 200 IPs / 10,000 requests = 200 rows; 400-path scan = 400 rows.

## What is the row cap?
- `waf_enforce_log_cap()`: time-based retention alone cannot bound the table, a big enough attack fills the retention window. The cap makes the worst case knowable.
- Do NOT use `COUNT(*)` (full index scan in InnoDB, running on a live request). `MAX(id)` is constant-time from the index and, being auto-increment, accurate enough as a cut point.
- The cap runs at 1/20 sweep frequency (retention cleanup 1/200): a safety valve that runs once in 200 during a flood is not a safety valve.

## How should reports count?
- Use `SUM(hit_count)`, not `COUNT(*)`; otherwise an attack looks like a handful of events.
