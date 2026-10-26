---
name: waf-legit-traffic-auto-ban
description: Plan (2026-10-26, from issue #1989): legitimate traffic must not end in an auto-ban: sub-resource fetches, refusals that feed the ban, staff lockout, the shield's answer, what the operator sees, self-rescue, scenario tests
---

## Why?
- Issue #1989 (2026-10-26, a live site on 2026.4.10 behind Cloudflare): every submitted-form image on a page (`files.attachment = 1`, the record's views in the recycle bin) was sent by `get_file.php` to `registration_entrance.php?send_to=`; one page view made 36 GETs of the sign-in screen in a second, the sensitive limit (30/min) answered 429 and registered an offence per refusal, the fifth offence banned the reader's address for 60 minutes. The pre-database shield then answered plain text `Forbidden` with no reference, the banned operator could not reach the panel, deleting the `banned_ip_addresses` row was not enough (`data/temp/ban_shield.txt` kept its `B` line), and the operator's address changed mid-session so the allow list did not help.
- The owner's review points (issue comment 3): legitimate flows that fill the counters, sub-resource requests, IP-based exemption, a banned operator's recovery, the refusal page, visibility, scenario tests.

## What is decided?
- Target branch `development` (issue header; owner, 2026-10-26); whether 2026.4.x gets a patch is an open question below.
- A sub-resource fetch (`Sec-Fetch-Dest` image/style/script/font/media, or the first `Accept` type of that kind on a browser without the header) of a file the visitor may not open is refused with 403, never sent to a sign-in screen; a frame, embed, fetch() or page opening still is (`waf/waf-subresource-sign-in-fetches.md`).
- A sub-resource fetch of a sign-in screen (`index.php`, `registration_entrance.php`, `membership_entrance.php`) is not counted on the sensitive allowance or the in-flight cap; a POST and every other GET of a sensitive script still are.

## What are the steps (one PR each)?
1. `get_file.php` sends a visitor to a sign-in screen only through `get_file_sign_in_redirect()`, which answers a sub-resource fetch with 403; `waf_fetch_is_subresource()`, `waf_sign_in_screens()` and `waf_sensitive_request_counts()` in `waf.php` keep such a fetch of a sign-in screen off the sensitive allowance and the in-flight cap. `tests/waf_subresource_test.php`. Claimed (this plan's first PR).

2. Refusals and the ban: a rate-limit refusal (`rate-sensitive`, `rate-api`, `concurrent`) registers at most one offence per subject per minute window, so a burst is one event and a ban needs refusals in five separate minutes; the signature scan, the sign-in lock and the prototype password keep one offence per refusal. An address whose session is staff, or that a staff member signed in from within the last day (the table that records it is chosen in the PR: `auth_tokens`, `log` or `user`), is never auto-banned: a `would-ban` row is logged instead. Tests for the counting rule. The thresholds (30/min, 5 offences in 10 min, 60 min) are put to the owner in that PR.

3. The shield's answer: the HTML page `waf_deny()` draws, as a twin in `includes/db_guard.php` (which may not load `waf.php`), saying the site's firewall refused the request and until when (`B <ip> <expires>` mirror lines; a line without the field still reads), with a reference written to `shield_pending.log` and stored by `waf_shield_drain()` in `waf_log.reference`; `path=` on every DENY line of `data/firewall.log` (`waf_deny()` and the shield twin).

4. The operator learns of a ban: an e-mail to the administrator address on each auto-ban (rule, address, sample requests, a link to the log), queued by `waf_register_offence()` in a pending file under `data/temp/` and sent by the next request that has `functions.php` or by the general job; a link from a ban row in the IP list to the log filtered by that address; a notice on the firewall screen when several addresses were banned by one rule within an hour.

5. Self-rescue: a signed single-use "lift this ban" link in that e-mail, answered by a root script that the IP-list check and the shield let through for a valid token only (design and the owner's answer to the question below first).

6. Scenario tests: `tests/waf_scenarios_test.php` playing the decision functions through "a page with N protected images", "staff whose address changes mid-session" and "a refresh after the ban"; a sandbox run of the three end-to-end scenarios of the issue only with a reason given in the PR.

## Open questions for the owner
- Q1. A submitted-form file in a public folder whose record no view shows at all (the views deleted or in the recycle bin): keep refusing it (today, 403 or sign-in), or fall back to the folder's permission? Recommendation: keep refusing. A form upload may be a CV or an identity scan in the default public upload folder; a site that uses such files as page images turns `attachment` off for them (the workaround applied on the live site), and a panel action for that can come later.
- Q2. Step 2's counting rule and the thresholds: asked in step 2's PR with the numbers it proposes.
- Q3. Step 5: may a root script with a valid single-use token be served to a banned address (the IP list and the shield letting that one script through)? Without it the link must be opened from another network.
- Q4. A 2026.4.x patch carrying `ebe10555` (#1320, designed views open a record's files) and step 1? The release is the owner's; nothing here assumes it.

## Where to start?
- Step 2 after step 1 lands; steps 3 and 4 are independent of 2 and of each other; 5 waits for Q3 and step 4; 6 last. Claim each as `[plan:waf-legit-traffic-auto-ban] <step> — <topic>`.
