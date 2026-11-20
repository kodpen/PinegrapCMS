---
name: application-review
description: Plan (owner 2026-11-20, poll 196): a form as an application programme: period, eligibility, weighted criteria, reviewers and jurors, blind review, combined scores, rounds, decisions; AR1a to AR8
priority: 6
---

## What is asked?
- Owner (2026-11-20, agent channel poll 196, message 10821): "Evet, plana girsin": programme, criteria, assignment, blind review, the reviewer screen, combining, rounds and decisions, report and API.

## What is there today?
- A form's submissions (`forms`, answers in `form_data`) have a Form Editor (`forms.form_editor_user_id`), watchers and Complete or Incomplete (`forms.complete`). The list's bulk actions are delete and export.
- A form has no period or deadline, only `enabled` and the rate limits (`waf/form-submission-limits.md`).
- Panel sight is `validate_forms_access()` and the folder rule (`pg_submitted_form_list_access()`), never per submission.
- The submitter sees their entry through the form list's viewer filter and the item view (`core/submitter-email-access.md`); My Account lists none; there is no correction link.
- Workspace approvals (`ws_approvals`, `ws_approval_people`) decide one record by any or all; their outside approvers (`ws_approval_guests`, `workspace_approval.php`) and the ERP quote link (`erp_quote_links`) are the signed-link pattern: a token from `pg_short_link_new_token()`, only its hash stored.
- `form.submitted` exists, no status-change event; submissions are not in `audit_log`.

## What binds it?
- A form with no programme works as today. With one, the submit path gains only the period check; eligibility is checked after the save and never refuses an entry.
- A reviewer reads only their own entries, checked on every read on the server. Others' scores stay hidden until the round closes; managers see all.
- Blind hides the name, e-mail, reference code, contact fields and the fields the programme names. Uploaded files show as uploaded: the screen says blind covers fields only.
- An external juror's link is per juror and programme, hash stored, valid until the programme closes, revocable, rate limited, and opens nothing else.
- The leniency correction is shown beside the raw mean and ranks only when the programme chooses it, from 5 scored entries of that reviewer.
- Decision mails go only on a person's press after a preview with counts; the result shows to the submitter only once mailed.
- Each score, conflict, override, decision and mail is an `audit_log` line (`pg_audit()`).

## What did the architect decide? (2026-11-20, agent channel message 10843)
- Schema: four tables, `application_programs`, `application_reviewers`, `application_entries`, `application_reviews`. Rounds and criteria as JSON on the programme row, criteria templates one `config_text` row; the juror link's hash on the reviewer row, the correction link's on the entry row; no column on `forms`. The owner said yes in poll 199 ("Evet, dört tablo eklensin", message 10845); the columns in `decisions-made-schema-44.md`.
- Reviewers: programme membership only, no permission column. A panel user opens "My reviews" through membership, with no Forms right, and it opens no Forms screen; a person with no panel sign-in works through the juror link. The role 3 ladder stays as it is.
- Export (owner, poll 200, message 10846): the entry's state, decision, combined score and criterion scores; never jurors' notes or names. Erase covers the four tables.
- Place: under Forms, run by whoever passes `validate_forms_access()` and the folder rule. Close-out file: `core/application-review.md`.

## What is built?
- Features: `application-review-features.md` (V1 to V16). Steps: `application-review-steps.md`. Claim as `[plan:application-review] <step> — <topic>`.

## What is left out?
- Interviews booked from the shortlist, AI summaries, an entry fee beyond the form's payment, public voting: each a later yes.

## Whose look?
- No pictures (`workflow/look-pictures-none.md`): the programme, "My reviews" and the ranking are seen on dev after AR3 and AR5.
