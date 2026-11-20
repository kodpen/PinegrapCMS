---
name: application-review-features
description: Plan application-review, features V1 to V16: programme, eligibility, correction link, criteria, assignment, conflicts, blind review, reviewers, jurors, combining, rounds, decisions
---

## What is a programme?
- (V1) A programme on a form: name, period, categories (from a choice field), rounds, reviewers per entry (N), blind on or off, decision texts.
- (V2) Outside the period the form refuses a new entry with the programme's text; a saved-for-later draft can be finished until the close.
- (V3) Eligibility rules over the answers (a required file, a number range such as age, a choice in a list). A failing entry is "not eligible" with its reasons and gets no reviewers; a person may override it with a note.
- (V4) A not-eligible entry's submitter gets a signed correction link for the named fields until the close; the entry is checked again.
- (V5) Weighted criteria (weights add up to 100), each scored 1 to 10 with an optional note; per round; criteria templates kept and copied.

## How are entries given out?
- (V6) Each eligible entry gets N reviewers from its category's pool: fewest assigned first, ties at random, never one reviewer twice in a round. A re-run after a change keeps the reviews already done.
- (V7) "I know this applicant" with a reason: the entry moves to another reviewer of the pool, and the conflict is kept.
- (V8) Blind as the plan's main file says.

## How does a reviewer work?
- (V9) My queue, progress, next and previous, scores saved as typed, one submit per entry; reminders 3 and 1 days before the round's end to those with open entries.
- (V10) An external juror is added by name and e-mail and works through a signed link on a page with no session (as `workspace_approval.php`), rate limited in `waf.php`.

## How are scores combined?
- (V11) Each review's weighted sum, then the entry's mean. Leniency correction: each reviewer's scores standardised against their own mean and spread, mapped back to 1 to 10.
- (V12) A spread above the programme's bound flags "needs consensus"; a manager settles it with a note.
- (V13) Ranking by the chosen score, per category; the shortlist by a cut-off score or a top N; the shortlist goes into the next round with its own criteria and reviewers.

## How are decisions made?
- (V14) Accepted, rejected or waitlisted, one by one or for the shortlist; decision e-mails from templates with the person's fields, under the main file's rule. A declined acceptance calls the next on the waitlist, by a press or by itself as the programme chooses.

## What else?
- (V15) The report: reviewer progress, score spread by criterion and reviewer, entries by state; Excel (`pg_xlsx_write_book()`).
- (V16) `GET /application-programs/{id}/entries` and its scores; the events `application.scored` and `application.decided` in the webhook and flow catalogues (`workspace/workspace-flows-adding.md`); the MCP tools.

Related: docs/memory/plans/application-review.md, docs/memory/plans/application-review-steps.md
