---
name: waf-legit-traffic-auto-ban-questions
description: Open questions of plans/waf-legit-traffic-auto-ban.md: attachments without a view, the offence counting rule and thresholds, a ban-lifting link for a banned address, a 2026.4.x patch
---

## Which questions are open?
- Q1. A submitted-form file in a public folder whose record no view shows at all (the views deleted or in the recycle bin): keep refusing it (today: 403 for a sub-resource fetch, the sign-in screen for a page opening), or fall back to the folder's permission? Recommendation: keep refusing. A form upload may be a CV or an identity scan in the default public upload folder; a site that uses such files as page images turns `attachment` off for them (the workaround applied on the live site of #1989), and a panel action for that can come later.
- Q2. Step 2's counting rule (one offence per subject per minute window for a rate-limit refusal) and the thresholds (30/min sensitive, 5 offences in 10 min, 60 min): asked in step 2's PR with the numbers it proposes.
- Q3. Step 5: may a root script with a valid single-use token be served to a banned address, the IP list and the shield letting that one script through? Without it the link must be opened from another network.
- Q4. A 2026.4.x patch carrying `ebe10555` (#1320, designed views open a record's files) and step 1? The release is the owner's; nothing in the plan assumes it.

## How is an answer recorded?
- The session whose PR is open when the answer comes writes it here as a decision with its reason and moves it to the plan's "What is decided?" list (`CLAUDE.md`, Fixed rules 6).
