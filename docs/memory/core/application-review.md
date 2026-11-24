---
name: application-review
description: Application review desk, the binding rules and what it covers (owner 2026-11-20, poll 196): V1 to V16 with the PR of each, what is left out, the words on screen
---

## What binds the desk?
- Owner (2026-11-20): a form may be run as an application programme (agent channel poll 196, `workflow/decisions-made-product-89.md`). A form with no programme works as before; with one, the submit path gains only the period check, and eligibility, checked after the save, never refuses an entry.
- A reviewer reads only their own entries, checked on every read on the server; others' scores stay hidden until the round closes; managers see all.
- Blind hides the name, e-mail, reference code, contact fields and the fields the programme names; uploads show as uploaded, and the screen says blind covers fields only.
- The leniency correction ("Corrected mean") is shown beside the raw mean and ranks only when the round chooses it, from a reviewer's fifth sent score.
- Decision e-mails go only on a person's press after a preview with counts; the result shows to the submitter only once mailed.
- Each score, conflict, override, decision and mail is an `audit_log` line (`pg_audit()`).

## What did the architect decide? (2026-11-20, agent channel message 10843)
- Four tables, no column on `forms` (owner yes, poll 199, `workflow/decisions-made-schema-44.md`); rounds and criteria as JSON on the programme row, criteria templates one `config_text` row (`application-review-store.md`).
- Reviewers are programme members, no permission column: a panel user opens "My reviews" with no Forms right, which opens no Forms screen; a person with no panel sign-in works through the juror link. The role 3 ladder stays.
- The programme sits under Forms, run by whoever passes `validate_forms_access()` and the folder rule.
- Export (owner, poll 200, `workflow/decisions-made-product-91.md`): state, decision, combined and criterion scores; never jurors' notes or names. Erase covers the four tables.

## Which feature landed where?
- V1 programme, V2 period, V3 eligibility and its override, V5 weighted criteria and templates: #10809 (rules), #10880 (`application-review-programme.md`).
- V4 correction link: #11173 (`application-review-correction-link.md`). V10 outside juror and its `waf.php` entry: #11173 (`application-review-jurors.md`).
- V6 assignment, V7 conflicts: #10809, #10962 (`application-review-reviewers.md`), #11024. V8 blind, V9 "My reviews" and reminders: #11024 (`application-review-my-reviews.md`).
- V11 combining, V12 "Needs consensus" and Settle, V13 ranking, the cut and the next round: #10809, #11115 (`application-review-ranking.md`).
- V14 decisions, e-mails, the waitlist call: #11149 (`application-review-decisions.md`).
- V15 report and Excel, V16 API routes, MCP tools, `application.scored` and `application.decided`: #11222 (`application-review-report.md`). V16 privacy (export, erase): #10832.

## Where does the desk differ from the plan's description?
- The correction link opens a secure card with the named fields, not the form's own page: a site form with some fields locked would change the custom form's renderer (`application-review-correction-link.md`).
- The Entries tab is a plain table, not the list table driver: the rank order is its meaning and the cut-off is a row across it (`application-review-ranking.md`).

## Which words are on screen?
- "Programme", "Entry", "Reviewer", "Round", "Corrected mean"; never "leniency", "token" or a column's name. One line of help a row or card.

## What is left out?
- Interviews booked from the shortlist, AI summaries, an entry fee beyond the form's payment, public voting: each needs a new owner yes.

Related: docs/memory/core/application-review-overview.md, docs/memory/workflow/decisions-made-product-89.md, docs/memory/workflow/decisions-made-schema-44.md
