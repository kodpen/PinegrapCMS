---
name: messages-area-sends-speed
description: How fast Communication › Sends reads 500,000 rows a queue (MA2 #9252, MA2b #9275): the architect's 300 ms, keyset bounds per branch, measured batches, the slow search and heading sorts left open
module: messages_sends
---

## What must a batch take?
- Architect (2026-11-13, #9081): MA2 measures a batch with 500,000 rows per queue inside the period; over 300 ms the keyset bound goes into each branch (a list table driver change) before the tab opens to everyone.
- The period is set inside every branch on its indexed time, so a batch never reads outside it ([[messages-area-sends]]).

## What was measured?
- MA2 (#9252) read the union whole: 5.5 s per first batch with 500,000 rows per queue in 7 days (MariaDB 10.11).
- MA2b (#9275) moved the work into the branches: the source gives `branches` and `branch_id` (`rid`, the row's own id), and the driver bounds, orders, limits and counts inside each one (`ui/list-table-driver.md`).
- Same data after MA2b: first batch 132 ms (count 1,000,000 included), next batch 14 ms; 24 hours 24 ms; any channel, state or source under 250 ms.

## What is still open?
- Search at that volume: 3.2 s, a LIKE scans every row (the old queue screen took 1.3 s on 500,000).
- A heading sort other than Date or Channel: about 2 s, a branch sorts its whole period.
- Not changed by MA2b: a question for the architect once a site meets it; an index or column for it is a schema question for the owner.
