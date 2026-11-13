---
name: canvas-measures
description: What a live page's read of a bound table costs at the 5000-row limit (CO5): time, memory and answer size of the bridge's rows call, and the bounds kept from it
module: guarded_frame
---

## How is it measured?
- `php tools/measure_page_read.php [rows] [runs]` on a throwaway install (`tools/setup_sandbox.sh`): an orders table with text, number, date, a row link to 50 products, a lookup of their price and two formulas, read through `ws_page_bridge_rows()` as a page asks; it removes its tables and binding at the end.

## What did it cost? (2026-11-13, sandbox, PHP 8.3, MariaDB, 4 cores)
- 5000 rows: about 300 ms a read (280-310 median, 355 at most), whatever the page size, conditions, sort or totals; about 10 MB of memory a read. 1000 rows: about 60 ms. Linear, about 0.06 ms a row.
- The time is reading every stored row and working out its formulas (`ws_wtable_rows()`, about 320 ms at 5000); lookups of a page of 500 take about 4 ms (`ws_wtable_joined_fill()`).
- An answer of 500 rows is about 175 KB of JSON, 100 rows about 35 KB.

## Which bounds follow?
- Kept as they are (`canvas-bridge.md`): 500 rows a read (100 when none is named) is a fifth of the 1 MB answer bound; 8 requests waiting; 120 requests a minute, which at 5000 rows is at most about 36 s of one PHP worker a minute for a page that asks without pause.
- The table's limit of 5000 rows (`ws_wtable_limits()`) stays: no limit had to move, so no question went to the owner.
- A page that reads often should ask with a larger `limit` and `from`, not page by 100: every read costs the whole table.

## What would make a read cheaper, if a site needs it?
- Each read works out the formulas of all rows to apply conditions and sort. Keeping the read rows for the request, or reading only the rows a page asks when it names no condition, sort or totals, would cut it; neither is built.
