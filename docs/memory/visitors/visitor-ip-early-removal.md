---
name: visitor-ip-early-removal
description: A visit's IP address set to 0 after config.visitor_ip_days (default 30, step 10.8) by the visitor cleanup, chunked with a cursor marker; dedup check safety, screens that show 0
---

## What does it do and why? (PR #500)
- Every `visitors` row kept the visitor's IP address (`INET_ATON()`, IPv4 only, 0 for IPv6) for as long as the record: 90 days by default, forever with retention 0. An address is personal data (KVKK/GDPR) and nothing needs it for long: the duplicate visit check looks at 30 minutes, and the reports, the daily summary and the hourly rollups never count it. Plan item 3 of docs/memory/plans/visitor-records-deferred.md.
- `config.visitor_ip_days` (2026.4.10, step 10.8, `SMALLINT UNSIGNED DEFAULT 30`): after that many days the general job sets `ip_address = 0`; the record, its source and its figures stay. 0 keeps the address as long as the record; allowed 1–3650 (Settings › SEO › Visitor Tracking, invalid input refused with a field error). Upgraded sites get 30, so older visits lose their address after the upgrade.
- Code: `includes/fn/visitor_ip.php`, called by `pg_visitor_cleanup_run()` after the removal (rows about to go are not written first). The cleanup now also runs when records are kept without a limit but the address has a period.

## How does a pass run?
- Boundary: `pg_visitor_retention_cutoff(ip_days)`, a midnight at least a day back, so never inside the 30-minute dedup window.
- Primary-key windows like the removal (`pg_visitor_id_window_end()`, `pg_visitor_last_id_before()`): `UPDATE visitors SET ip_address = 0 WHERE id window AND start < cutoff AND (stop < cutoff OR start < cutoff − 1 day) AND ip_address <> 0`. InnoDB 5,000 rows + 20 ms pause, MyISAM 1,000 + pause as long as the chunk. Works the same on a split and an unsplit table (`start < cutoff` prunes later months).
- A visit still going on at the boundary keeps its address, so a zeroed row can never become a fresh `ip_address = 0` match for an IPv6 visitor's duplicate check; the next day's pass picks it up (it started over a day before the new boundary).
- Marker `data/temp/visitor_ip.json`: cutoff, cursor, from, done, cleared. One pass per boundary (once a day); the next starts one day before the previous boundary, so each visit is read about twice in all. A run that runs out of time continues from the cursor; the cleanup reports a backlog until the pass is done. A table renumbered from 1 (cursor past `MAX(id)`) gets a pass from the start. Losing the marker costs one pass from the start.
- Held while the move copies the table (`pg_visitor_move_last()` state `copying`): the move re-copies only rows whose `stop_timestamp` changed, so a row zeroed after it was copied would keep its address in the copy.

## What do screens show for a zeroed address?
- `view_visitor.php`: "Removed after N days" when the visit is older than the period, else "-" (IPv6). Never "0.0.0.0".
- Visitor report IP filter: 0 reads as empty (`CASE WHEN ip_address = 0 THEN ""`), like the order report.
- Database Engine › Visitor records: row "IP addresses" = "Taken out after N days" / "Kept as long as the record". Result text and the activity log name the count ("general job took the IP address out of N visitor record(s) older than D days").

## How fast is it? (sandbox, MariaDB 10.11, 300,000 rows over 120 days)
- 220,000 addresses in two runs of the 2–3 s budget on InnoDB split and unsplit, three runs on MyISAM; the next day's pass 0.04 s. Simulated visits during the pass: max wait 9.6 ms (InnoDB), 16.4 ms (MyISAM).

Related: visitor-retention-and-cleanup, visitor-counter-dedup-key, visitor-monthly-partitions
