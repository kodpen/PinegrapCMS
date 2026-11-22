---
name: waf-queue-status-bucket
description: The walk-in queue's status read (queue.php?n=&t=&read=1) skips the sensitive allowance and open-request cap; own bucket of 240/min per address, excess gets a 429, no offence
---

## What went wrong?
- The person's page of a walk-in queue number reads its state every 15 seconds (4 a minute). `queue.php` is in `waf_sensitive_scripts()`, so on a shared address (venue Wi-Fi, carrier NAT) about eight open pages spent the 30 a minute sensitive allowance and the rest were refused, the refusals counted as offences (WQ3b, #11144).

## Which request is the read?
- `waf_queue_status_read($method, $script, $query)` (waf.php, pure): a GET or HEAD of `queue.php` with a scalar `n` and a `read` that is neither empty nor `0`. Opening the page (no `read`), the take page (`place=`), taking a number, "Leave the queue", "I'm on my way" and every staff action are POSTs or other GETs and stay sensitive.
- The lobby screen's read (WQ6) joins this function and the same bucket.

## What does the firewall do with it?
- `waf_sensitive_request_counts()` takes the query as a fifth argument (`waf_is_sensitive_request()` passes `$_GET`) and answers false for the read: no sensitive count, no open-request slot. The global limit still counts it.
- `waf_handle_rate_queue_status()` (init stage, beside `waf_handle_rate_beacon()`; verified crawlers and uncounted addresses skip it) counts per `waf_ip_subject()` in scope `queue_status`, `WAF_QUEUE_STATUS_RATE_LIMIT` = 240 a minute (about 60 people at one read every 15 s).
- Over it: the script is not run, the answer is an empty 429 with `Retry-After: 15`. `assets/js/queue_page.js` ignores an answer that is not ok, keeps what the page shows and reads again at its next tick.
- As the beacon bucket (`waf-beacon-bucket.md`, the same `waf_beacon_bucket_answer()`): no offence, no `data/firewall.log` line, one `waf_log` row a minute, rule `rate-queue-status`; Monitor mode writes `would-rate` and runs the script.

## How cheap is the read itself?
- `queue.php` defines `PG_NO_SESSION` for it (no session file per read), finds the row by its primary key (`pg_waiting_ticket_find()`) and compares the token's sha256, reads the place, writes nothing. `token_hash` keeps no index: the row is not found by the token alone (`workflow/decisions-made-schema-47.md`).

## How was it checked?
- `tests/waf_queue_status_test.php` (the limit, which requests are the read, the sensitive count). No live server in the cloud session: the 429 and the session-less read were not run over HTTP.

Related: docs/memory/waf/waf-beacon-bucket.md, docs/memory/commerce/walk-in-queue-number-page.md, docs/memory/waf/waf-inflight-request-cap.md
