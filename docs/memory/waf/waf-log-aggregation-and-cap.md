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
- A ban row (`ban`, `would-ban`) is written beside the row of the request that earned it, so the screen's request cards and the toolbar's request count leave it out; bans are counted on their own (`pg_waf_log_list_request_actions()`, #5859).

## Do the two search boxes of the log screen agree?
- Yes: the toolbar search (which also filters the summary) goes through `pg_waf_log_list_search()`, the table box's rule: every word, address, method, URL, user agent, rule, reference, target, match or an action's shown name (#5854). A filter that finds nothing says so with a link that clears it, not "no events in this period".

## How are request values written into a row?
- Through `waf_log_text($value, $bytes)`: cut to the column's bytes on a character boundary, every byte outside a valid UTF-8 character written as `?`. `waf_log_event()` and `waf_shield_drain()` use it for method, URL, target, match and user agent. The table is utf8mb4 and the request runs under the server's own sql_mode (strict by default), where one invalid byte refused the whole silenced INSERT: a PHP-decoded `%bf%27` probe, a raw high byte in the path or a non-ASCII user agent cut mid-character by `substr()` left no row at all (`tests/waf_log_text_test.php`).
