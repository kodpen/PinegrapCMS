---
name: application-review-programme
description: Application programme settings (AR2): the Forms screens, the period check in the submit path, the entry row with eligibility, the override, criteria templates
module: application_programs
---

## Where are a programme's settings?
- Forms › Application programmes (`view_application_programs.php`, printed list) and a programme's screen (`edit_application_program.php`), both behind `validate_forms_access()`; a role 3 user sees only programmes on forms in folders they may edit (`pg_application_program_forms()`, `check_edit_access()`).
- Code: `includes/fn/application_programs.php`; pure parts tested in `tests/application_programs_test.php`.

## What does the programme row keep?
- `rounds` JSON: list of `{criteria: [{id, name, weight}], reviewers, ends_at, spread, rank_by, cut}` (the last three: `application-review-ranking.md`); criterion ids `c1`, `c2`… stay across saves. `reviewers_per_entry` mirrors round 1.
- `eligibility` JSON: rules of `pg_application_eligibility()` keyed by the form field's name. Typed as "18-30" (either end may be left out) or "A, B" (`pg_application_rule_from_input()`).
- `waitlist_auto`: the Decision e-mails card's switch (`application-review-decisions.md`).
- `blind_fields` JSON: field names; `texts` JSON: `outside`, and `accepted`/`rejected`/`waitlisted` each `{subject, body}`; `category_field_id`: a pick list, radio button or check box field.
- Criteria templates: `config_text` row `application_criteria_templates`, list of `{name, criteria}`; "Save as a template" names it after the programme and round, same name replaced, 50 kept.

## When does the form refuse an entry?
- Status 0 (draft): never, the form works as before. Status 1: before `opens_at` and from `closes_at` on. Status 2 (closed): always (`pg_application_period_state()`).
- `custom_form.php` refuses a new entry or a new draft with the programme's `texts.outside` (fallback "This form does not take entries at the moment.").
- A draft saved for later is completed in `edit_submitted_form.php`; the submitter may finish it until the close, a forms manager always.

## What happens after the save?
- A completed submission (`complete` 1, in `custom_form.php` or when a draft is completed) gets its `application_entries` row in the open programme: category from the category field's first answer, `eligible` 1 or 2 with `{reasons}` in `eligibility_note`. `INSERT IGNORE` on the unique `form_id`.
- Eligibility never refuses: a failing entry is saved and marked not eligible.

## How is a not eligible entry let in?
- The submitted form's screen shows an "Application" card to forms managers: pill Eligible, Not eligible (amber), Let in, with the reasons in words (`pg_application_reason_text()`).
- A not eligible entry can be sent a correction link from the same card (`application-review-correction-link.md`).
- "Let it in" needs a note: `eligible` 3, the note beside the reasons as `override {note, by, at}`, an `audit_log` line on `application_entry`.

## How does a programme open and close?
- "Open for entries" refuses while `pg_application_program_missing()` lists `period` (no closing time) or `criteria` (no round, or weights not 100), or another programme of the same form is open.
- "Add programme" opens a drawer on the list (name and form; a refused add reopens it with `?add=1`); the programme starts as a draft.
- "Close" sets status 2. Delete is in the list row's "more" menu, last and danger with a confirm, only for a draft with no entries. Each create, save, status change and delete is an `audit_log` line on `application_program`.

Related: docs/memory/core/application-review-rules.md, docs/memory/core/application-review-store.md, docs/memory/core/application-review.md
