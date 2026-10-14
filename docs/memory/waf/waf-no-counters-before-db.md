---
name: waf-no-counters-before-db
description: Why a pre-DB request counter ("siege mode") was tried and removed; the design flaws to read before reintroducing one.
---

## Should I build a counter before the DB connection?
- No. One attempt (siege mode) was removed in review. Before writing it again, read why it did not work:
  - The window never closed. `FILE_APPEND` advances mtime, so the "open a new window" test measured silence since the LAST request, not time since the first. 10 req/min crossed the 60 threshold in 6 minutes, and since `router.php` runs before `get_file.php`, every image was a count (a 30-image page = 30 counts).
  - It ran twice per request: `router.php` is a front controller and the page it routes to includes `init.php`. No latch, so the threshold was effectively halved. (`pg_db_guard_check()` now carries `static $done`.)
  - An attacker was exempt with a single `X-Forwarded-For` header. The header makes the address unverifiable and the count was skipped to avoid counting a wrong address, so the only counted population was normal browsers that never send it. A limit the attacker can skip and the customer cannot is worse than no limit.
  - A routine MySQL restart (2006) left counting open for 10 minutes.
  - No allow list at this layer; the way out of a false positive was FTP.
- The first two were fixable. The third is not a bug but a design that cannot be solved at this layer: the list saying which proxy may speak for someone else lives in the database. Where the address cannot be trusted, no counting is done.

Related: docs/memory/waf/waf-ban-shield-mirror.md
