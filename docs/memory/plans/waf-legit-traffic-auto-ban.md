---
name: waf-legit-traffic-auto-ban
description: Plan (2026-10-26, issue #1989): legitimate traffic must not end in an auto-ban: sub-resource fetches, refusals feeding the ban, staff lockout, the shield's page, visibility, self-rescue
---

## Why?
- Issue #1989 (a live 2026.4.10 site behind Cloudflare): every submitted-form image on a page was sent by `get_file.php` to the sign-in screen; one page view made 36 GETs of it in a second, the sensitive limit (30/min) answered 429 with an offence per refusal, and the fifth banned the reader's address for an hour. The shield answered plain text `Forbidden`, the banned operator could not reach the panel, deleting the ban row left the mirror's `B` line, and the operator's address changed mid-session so the allow list did not help.
- The owner's review points and the code sweep of 2026-10-26: `waf-legit-traffic-auto-ban-findings-counting.md`, `…-findings-writes.md`, `…-findings-lockout.md`. Questions: `…-questions.md`.

## What is decided?
- Target branch `development`; no 2026.4.x patch; files without a view stay refused; no script is served to a banned address (owner, 2026-10-26/27; `…-questions.md`).
- A sub-resource fetch of a protected file answers 403, never the sign-in redirect; such a fetch of a sign-in screen is not counted as sensitive (`waf/waf-subresource-sign-in-fetches.md`).

## What are the steps (one PR each)?
1. `get_file.php` sends a visitor to a sign-in screen only through `get_file_sign_in_redirect()`, which answers a sub-resource fetch with 403; `waf_fetch_is_subresource()`, `waf_sign_in_screens()` and `waf_sensitive_request_counts()` in `waf.php` keep such a fetch off the sensitive allowance and the in-flight cap. Claimed by #2011.

2a. Refusals and the ban (findings-counting): a rate-limit refusal (`rate-sensitive`, `rate-api`, `concurrent`, the password-reset ceiling) registers at most one offence per subject per minute window; an address with a staff session or a staff sign-in in `auth_tokens` within the last day is never auto-banned (a `would-ban` row instead); Release and Allow clear the offence bucket. Thresholds and the ladder go to the owner (Q2). Done (#2071).

2b. The counters' subjects and endpoints: the `api.php` allowance exempts staff, not any session; a private or reserved visitor address is infrastructure; `workspace_guest.php` and `shipworks.php` get the credential-endpoint allowance; the web root's `index.php` told from the panel sign-in. Done (#2108).

3. What a ban writes (findings-writes): the shield's HTML page with the ban's end and a reference, `B <ip> <expires>` lines refreshed by the general job, the rule in the ban note and row, `path=` and `status=` on `data/firewall.log` lines. Done (#2157); left over: `status=429` on the DENY line of `includes/fn/custom_form.php` (~4166), held by #1997 then.

4. The operator learns of a ban (findings-lockout): an e-mail on each auto-ban (rule, address, sample requests, log link) queued by `waf_register_offence()` in a `data/temp/` pending file and sent by the next request that has `functions.php` or by the general job; a link from a ban row to the log filtered by its address; a notice when several addresses were banned by one rule within an hour. Done (#2167, `waf/waf-ban-notice.md`).

5. Self-rescue and self-lockout guards (findings-lockout): a CLI root script `waf_unban.php` (cron-gated); Settings › Firewall refuses a block entry that covers the operator's own address; the sign-in throttle's address lock honours the allow list (owner); nothing is served to a banned address (Q3).

6. Scenario tests: `tests/waf_scenarios_test.php` playing the decision functions through "a page with N protected images", "staff whose address changes mid-session" and "a refresh after the ban"; a sandbox run only with a reason in the PR.

## Where to start?
- 2b after 2a lands; 3 and 4 independent of 2 and of each other; 5 after 4; 6 last. Claim: `[plan:waf-legit-traffic-auto-ban] <step> — <topic>`.
