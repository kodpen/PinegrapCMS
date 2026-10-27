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
- Thresholds stay; a `concurrent` refusal is no offence; a rate ban stays off the x4 ladder (owner, 2026-10-27, Q2; `workflow/decisions-made-waf.md`).
- The global limit stays counted at the router stage, before the session: its signed-in exemption reaches only directly requested scripts, and the texts say so (owner, 2026-10-27, Q5; `workflow/decisions-made-waf.md`).

## What are the steps (one PR each)?
1. Sub-resource fetches answer 403 and stay off the sensitive allowance and the in-flight cap. Done (#2011).
2a. One offence per subject and minute for a rate refusal; a staff address is never auto-banned (`would-ban`); Release and Allow clear the offences. Done (#2071, `waf/waf-offence-fold-and-staff-address.md`).
2b. The counters' subjects and endpoints: staff off the `api.php` allowance, private addresses uncounted, the credential endpoints, the web root's `index.php`. Done (#2108, `waf/waf-counter-subjects-and-endpoints.md`).
2c. A `concurrent` refusal registers no offence; a rate ban is always the base length. Done (#2201).
3. What a ban writes: the shield's HTML page, refreshed `B` lines, the rule in the note and row, `path=` and `status=` on `data/firewall.log` lines. Done (#2157).
4. The operator learns of a ban: an e-mail per auto-ban, a link from the ban row to its log, a notice for a rule's burst. Done (#2167, `waf/waf-ban-notice.md`).
5. Self-rescue: CLI `waf_unban.php`, Settings refuses a block entry covering the operator's own address, the sign-in lock spares allow-listed addresses. Done (#2187, `waf/waf-self-rescue.md`).
6. Scenario tests, `tests/waf_scenarios_test.php`: a page with forty protected images, sustained refusals, a staff address changing mid-session, a refresh after the ban. Done (#2212).

## What is left?
- `status=429` on the DENY line of `pg_cf_throttle_log()` (`includes/fn/custom_form.php` ~4166), the one `data/firewall.log` writer without it: one line, held by #1997 while that PR edits the file.
- One owner question, asked 2026-10-27 (#2238), open: a release action for the sign-in throttle's address lock beside Unlock Sign-in on `edit_user.php` (today `pg_login_unlock()` clears the account subjects only; the address lock ends by itself or needs the allow list).

## Where to start?
- Nothing to claim until #1997 lands or the answer comes; then `[plan:waf-legit-traffic-auto-ban] leftover — <topic>`. With the line in and the question answered, the plan's lasting content is in `waf/` and `workflow/` and the plan files are deleted.
