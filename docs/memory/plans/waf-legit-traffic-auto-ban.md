---
name: waf-legit-traffic-auto-ban
description: Plan (2026-10-26, issue #1989): legitimate traffic must not end in an auto-ban: sub-resource fetches, refusals feeding the ban, staff lockout, the shield's page, visibility, self-rescue
---

## Why?
- Issue #1989 (a live site on 2026.4.10 behind Cloudflare): every submitted-form image on a page (`files.attachment = 1`, the record's views in the recycle bin) was sent by `get_file.php` to the sign-in screen; one page view made 36 GETs of it in a second, the sensitive limit (30/min) answered 429 with an offence per refusal, and the fifth banned the reader's address for an hour. The shield answered plain text `Forbidden` with no reference, the banned operator could not reach the panel, deleting the `banned_ip_addresses` row left the `B` line in `data/temp/ban_shield.txt`, and the operator's address changed mid-session so the allow list did not help.
- The owner's review points (issue comment 3): flows that fill the counters, sub-resource requests, IP-based exemption, a banned operator's recovery, the refusal page, visibility, tests. Questions: `waf-legit-traffic-auto-ban-questions.md`.

## What is decided?
- Target branch `development` (issue header; owner, 2026-10-26).
- A sub-resource fetch of a file the visitor may not open is refused with 403, never sent to a sign-in screen; a sub-resource fetch of a sign-in screen is not counted on the sensitive allowance or the in-flight cap (`waf/waf-subresource-sign-in-fetches.md`).

## What are the steps (one PR each)?
1. `get_file.php` sends a visitor to a sign-in screen only through `get_file_sign_in_redirect()`, which answers a sub-resource fetch with 403; `waf_fetch_is_subresource()`, `waf_sign_in_screens()` and `waf_sensitive_request_counts()` in `waf.php` keep such a fetch of a sign-in screen off the sensitive allowance and the in-flight cap; `tests/waf_subresource_test.php`. Claimed (this plan's first PR).

2. Refusals and the ban: a rate-limit refusal (`rate-sensitive`, `rate-api`, `concurrent`) registers at most one offence per subject per minute window, so a burst is one event and a ban needs refusals in five separate minutes; the signature scan, the sign-in lock and the prototype password keep one per refusal. An address whose session is staff, or that a staff member signed in from within the last day (`auth_tokens`, `log` or `user`: chosen in the PR), is never auto-banned: a `would-ban` row is logged instead. Tests for the counting rule; the thresholds are put to the owner in that PR (Q2).

3. The shield's answer: the HTML page `waf_deny()` draws, as a twin in `includes/db_guard.php` (which may not load `waf.php`), naming the site's firewall and the ban's end (`B <ip> <expires>` mirror lines; a line without the field still reads), with a reference written to `shield_pending.log` and stored by `waf_shield_drain()` in `waf_log.reference`; `path=` on every DENY line of `data/firewall.log` (`waf_deny()` and the shield twin).

4. The operator learns of a ban: an e-mail to the administrator address on each auto-ban (rule, address, sample requests, a link to the log), queued by `waf_register_offence()` in a pending file under `data/temp/` and sent by the next request that has `functions.php` or by the general job; a link from a ban row in the IP list to the log filtered by that address; a notice when several addresses were banned by one rule within an hour.

5. Self-rescue: a signed single-use "lift this ban" link in that e-mail, answered by a root script that the IP-list check and the shield let through for a valid token only (Q3 first).

6. Scenario tests: `tests/waf_scenarios_test.php` playing the decision functions through "a page with N protected images", "staff whose address changes mid-session" and "a refresh after the ban"; a sandbox run of the issue's end-to-end scenarios only with a reason in the PR.

## Where to start?
- Step 2 after step 1 lands; 3 and 4 are independent of 2 and of each other; 5 waits for Q3 and step 4; 6 last. Claim each as `[plan:waf-legit-traffic-auto-ban] <step> — <topic>`.
