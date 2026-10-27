---
name: waf-legit-traffic-auto-ban-questions
description: Questions of plans/waf-legit-traffic-auto-ban.md with the owner's answers: attachments without a view, a script for a banned address and a 2026.4.x patch (answered), thresholds (open)
---

## Which questions are open?
- Q1. Answered (owner, 2026-10-27, "Q1 önerin onay"): a submitted-form file in a public folder whose record no view shows stays refused (403 for a sub-resource fetch, the sign-in screen for a page opening); no fallback to the folder's permission, because a form upload may be a CV or an identity scan in the default public upload folder. A site that uses such files as page images turns `attachment` off for them. Recorded in `workflow/decisions-made-files.md`.
- Q2. Step 2a's counting rule (one offence per subject per minute window for a rate-limit refusal, so a ban needs refusals in six minutes of a ten-minute slot) and the thresholds (30/min sensitive, 5 offences in 10 min, 60 min, the x4 ladder also for rate bans): keep them, or should a `concurrent` refusal register no offence at all and a rate ban stay off the ladder?
- Q3. Answered (owner, 2026-10-27, "Q3 gerek yok"): no script is served to a banned address, token or not; the IP list and the shield stay without exceptions. Step 5 keeps the CLI script and the settings guard; the e-mail of step 4 names them.
- Q4. Answered (owner, 2026-10-27, "2026.4.x yaması istemiyorum"): no 2026.4.x patch; the fixes ship with the open version.

## How is an answer recorded?
- The session whose PR is open when the answer comes writes it here as a decision with its reason and moves it to the plan's "What is decided?" list (`CLAUDE.md`, Fixed rules 6).
