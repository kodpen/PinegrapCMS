---
name: waf-legit-traffic-auto-ban-questions
description: Questions of plans/waf-legit-traffic-auto-ban.md with the owner's answers: attachments without a view, a script for a banned address and a 2026.4.x patch (answered), thresholds (answered)
---

## Which questions were asked?
- Q1. Answered (owner, 2026-10-27, "Q1 önerin onay"): a submitted-form file in a public folder whose record no view shows stays refused (403 for a sub-resource fetch, the sign-in screen for a page opening); no fallback to the folder's permission, because a form upload may be a CV or an identity scan in the default public upload folder. A site that uses such files as page images turns `attachment` off for them. Recorded in `workflow/decisions-made-files.md`.
- Q2. Answered (owner, 2026-10-27, "önerilerini destekliyorum" to option (b)): the thresholds stay (30/min sensitive, 5 offences in 10 min, 60 min, one offence per subject and minute for a rate refusal), a `concurrent` refusal registers no offence and a rate ban stays off the x4 ladder. Reason: two campaign days with the chat open turned a legitimate address's 60-minute ban into 240 minutes, and an office NAT trips the concurrent cap. Done in step 2c (#2201); recorded in `workflow/decisions-made-waf.md`.
- Q3. Answered (owner, 2026-10-27, "Q3 gerek yok"): no script is served to a banned address, token or not; the IP list and the shield stay without exceptions. Step 5 keeps the CLI script and the settings guard; the e-mail of step 4 names them.
- Q4. Answered (owner, 2026-10-27, "2026.4.x yaması istemiyorum"): no 2026.4.x patch; the fixes ship with the open version.

## How is an answer recorded?
- The session whose PR is open when the answer comes writes it here as a decision with its reason and moves it to the plan's "What is decided?" list (`CLAUDE.md`, Fixed rules 6).
