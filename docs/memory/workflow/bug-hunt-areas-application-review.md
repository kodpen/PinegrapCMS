---
name: bug-hunt-areas-application-review
description: Bug hunt of the application review desk (Forms › Application programmes, named by the architect 2026-11-26, request 3016): five faults fixed in #11757, what was read clean, what was left
---

## What was the area?
- The desk as `core/application-review-overview.md` lists it: `includes/fn/application_*.php`, `edit_application_program.php`, `view_application_programs.php`, `my_reviews.php`, `application_juror.php`, `application_fix.php`, `includes/api/resources/application_programs.php`. Given by coordinator k2 (2026-11-26).

## What did it fix? (#11757 lists each with file and line)
- An outside juror's link expired at Close when the round had no end set: jurors were locked out of a closed programme's reviewing and of every round from the second. Only a set and passed end ends it (`pg_application_round_over()`).
- A deleted submission kept its entry and reviews: reviewers scored answers that were gone and a blank entry was ranked, decided and counted (`pg_application_entry_delete_for_form()`).
- A stale "Open for entries" or "Close" press fell through to the Set-up save and drew the form blank; a Save from it wiped the set-up.
- A panel reviewer still sent scores once the round was over, after the juror link had expired (`pg_application_review_open()`).
- "Let it in" pressed twice wrote two audit lines.
- Later, issue #11758 (#11773): two "Call the next" presses at the same moment accepted two waitlisted entries for one declined place; the call now runs under the declined entry's lock.

## What was read clean?
- Reads of "My reviews" and the juror page: the review is joined on the signed-in user or the link's reviewer row, not removed, never a conflict or a draft programme; autosave and posts check the form token.
- Sending: the conditional `UPDATE … status IN (0, 1)` sends once; the round is recombined on each send. Conflicts are given out again for that entry only.
- Assignment: kept, dropped and new reviews; a second run changes nothing. Remove keeps sent reviews and conflicts.
- Combining, ranking, the cut (ties never split) and the next round; decisions conditional on the decision read; mails claimed by `mailed_at`; the result shown only to the submitter once mailed.
- The programme screen's posts are scoped to the programme (`program_id` in every write); the folder rule on every request. API routes match the screens' reach (`forms:read` given as the Forms right, the folder filter for role 3).
- Escaping on the desk's screens and the Excel sheets (text cells never formulas).

## What was left?
- Changing a round's criteria or weights after reviews were sent keeps the sent totals: a product question, not built.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/core/application-review-overview.md
