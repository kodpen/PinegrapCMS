---
name: waf-legit-traffic-auto-ban
description: Plan (2026-10-26, issue #1989): legitimate traffic must not end in an auto-ban: sub-resource fetches, refusals feeding the ban, staff lockout, the shield's page, visibility, self-rescue
---

## Why?
- Issue #1989 (a live 2026.4.10 site behind Cloudflare): `get_file.php` sent every submitted-form image on a page to the sign-in screen; one page view made 36 GETs in a second, the sensitive limit (30/min) registered an offence per 429 and the fifth banned the reader's address for an hour. The shield answered plain text `Forbidden`, the banned operator could not reach the panel, deleting the ban row left the mirror's `B` line, and the operator's address changed mid-session.
- The owner's review points and the code sweep of 2026-10-26: `waf-legit-traffic-auto-ban-findings-counting.md`, `…-findings-writes.md`, `…-findings-lockout.md`. Questions: `…-questions.md`.

## What is decided?
- Target branch `development`; no 2026.4.x patch; files without a view stay refused; no script is served to a banned address (owner, 2026-10-26/27; `…-questions.md`).
- A sub-resource fetch of a protected file answers 403, never the sign-in redirect; such a fetch of a sign-in screen is not counted as sensitive (`waf/waf-subresource-sign-in-fetches.md`).
- Thresholds stay; a `concurrent` refusal is no offence; a rate ban stays off the x4 ladder (owner, 2026-10-27, Q2; `workflow/decisions-made-waf.md`).
- The global limit stays counted at the router stage, before the session: its signed-in exemption reaches only directly requested scripts, and the texts say so (owner, 2026-10-27, Q5; `workflow/decisions-made-waf.md`).
- No release action for the sign-in throttle's address lock (2026-10-27, Q6): the firewall log's suspects list offers Allow on a `login-lock` row of an address with a staff sign-in, a named activity or an order, and an allow-listed address is not counted (#2187), so the lock lifts at once; it needs 50 failures in 15 minutes and ends after 60.

## What are the steps (one PR each)?
1. Sub-resource fetches answer 403 and stay off the sensitive allowance and the in-flight cap. Done (#2011).
2a. One offence per subject and minute for a rate refusal; a staff address is never auto-banned (`would-ban`); Release and Allow clear the offences. Done (#2071, `waf/waf-offence-fold-and-staff-address.md`).
2b. Counter subjects and endpoints: staff off the `api.php` allowance, private addresses uncounted, credential endpoints, the web root's `index.php`. Done (#2108, `waf/waf-counter-subjects-and-endpoints.md`).
2c. A `concurrent` refusal registers no offence; a rate ban is always the base length. Done (#2201).
3. What a ban writes: the shield's HTML page, refreshed `B` lines, the rule in the note and row, `path=` and `status=` on `data/firewall.log` lines. Done (#2157).
4. The operator learns of a ban: an e-mail per auto-ban, a link from the ban row to its log, a notice for a rule's burst. Done (#2167, `waf/waf-ban-notice.md`).
5. Self-rescue: CLI `waf_unban.php`, Settings refuses a block entry covering the operator's own address, the sign-in lock spares allow-listed addresses. Done (#2187, `waf/waf-self-rescue.md`).
6. Scenario tests (`tests/waf_scenarios_test.php`): forty protected images, sustained refusals, a staff address changing mid-session, a refresh after the ban. Done (#2212).

## What is left?
- `status=429` on the DENY line of `pg_cf_throttle_log()` (`includes/fn/custom_form.php` ~4166), the one `data/firewall.log` writer without it: one line, held by #1997 while that PR edits the file.
- The memory still to carry Q6: `…-questions.md`, `…-findings-lockout.md`, `waf/waf-login-throttle.md` ("no screen releases the address lock": Allow and the allow list do), `workflow/decisions-made-waf.md` (the decision with its reason).

## Where to start?
- The memory line is free to claim (`[plan:waf-legit-traffic-auto-ban] leftover — <topic>`); the `status=429` line waits for #1997. With both in, the lasting content is in `waf/` and `workflow/` and the plan files are deleted.
