---
name: application-review-screens-review
description: Plan application-review, how reviewing and deciding are put together (AR3 to AR6): "My reviews" and the scoring screen, the outside juror's page, the ranking list, the decisions and their e-mails
---

## Which screens, and by which rules?
- AR3: "My reviews" and the scoring screen. AR4: the juror's page, the correction link. AR5, AR6: the Entries and Decisions tabs. The programme's own screens and the words: `application-review-screens.md`.
- `ui/screen-composition-rules.md`; parts are copied from the Design examples screen, the section named in brackets.

## How does a reviewer work? (AR3, AR4)
- "My reviews" is its own entry in the panel's menu for a member: a programme a card with a progress bar and the days left, then the queue as rows with a pill (To do, Started, Sent).
- The scoring screen: two columns, the entry's answers at the left (hidden fields read "Hidden"), the criteria at the right, each a 1 to 10 segmented choice and a folded note; the weighted total under them. Foot: "Send the review" fill, Previous and Next quiet, "I know this applicant" quiet, opening a reason field. One column on a phone, criteria under the answers.
- The outside juror's page (AR4) is the same queue and scoring screen in the panel look with no menu and no top bar, the programme's name as its head. The correction link's page is the form's own page with only the named fields open.

## How are ranking and decisions laid out? (AR5, AR6)
- Entries: a list on the list table driver, per category by a segmented choice: rank, entry, reviews done of N, raw mean, corrected mean, spread, pills "Not eligible", "Needs consensus", "Shortlisted". The cut-off is a line across the list with its control above (score or top N). Row actions: Open, Override (eligibility), Settle (consensus), each a drawer with a note field.
- Decisions: the shortlist with a decision select a row and bulk "Accept", "Reject", "Waitlist". "Send the decision e-mails" is the fill; it opens a drawer of numbered steps: the counts per decision, a preview per template, Send.

Related: docs/memory/plans/application-review-screens.md, docs/memory/plans/application-review-steps.md
