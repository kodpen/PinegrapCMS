---
name: waf-beacon-bucket
description: Beacons (page_view.php, consent_answer.php) skip the sensitive allowance and open-request cap; own bucket of 240/min, excess gets an empty 204, no offence
---

## What went wrong?
- `page_view.php` (the view of a cached page, the page speed figures, site search and experiment clicks) and `consent_answer.php` (the cookie notice's answer) are POSTs, so `waf_sensitive_request_counts()` counted them on the 30 a minute sensitive allowance and the cap on open requests. A shared address spent both by reading pages and was refused the sign-in it came for; the refusals climbed to a ban (#9125).

## Which scripts are beacons?
- `waf_beacon_scripts()` (waf.php): `page_view.php`, `consent_answer.php`. The census: the only scripts a site page posts to without a click (`sendBeacon` in `pg_vitals`, `pg_consent.js`, the page cache, site search and experiment snippets). The booking widget, wishlist and passkey post after a click and stay sensitive.
- A new beacon script joins this list; `tests/waf_beacon_test.php` names it.

## What does the firewall do with one?
- `waf_sensitive_request_counts()` answers false for a beacon script, whatever the method: no sensitive count, no open-request slot. The global limit (`waf_rate_limit_requests`) still counts them.
- `waf_handle_rate_beacon()` (init stage, same switch as the other limits; verified crawlers and uncounted addresses skip it) counts them per `waf_ip_subject()` in scope `beacon`, `WAF_BEACON_RATE_LIMIT` = 240 a minute.
- Over it (`waf_beacon_bucket_answer()`, pure): the script is not run and the answer is an empty 204, what the script answers anyway; the page's script never reads it. No offence, no `data/firewall.log` line (a fail2ban filter on DENY would ban the readers this spares), one `waf_log` row a minute: rule `rate-beacon`, the first request over the limit only. Monitor mode writes `would-rate` and runs the script.

Related: docs/memory/waf/waf-subresource-sign-in-fetches.md, docs/memory/waf/waf-inflight-request-cap.md
