---
name: custom-form-shown-when
description: "Shown when" rule of a designed form field (rules.shown_when): hidden row and disabled controls in the browser, value dropped and checks skipped on the server, cascade, designer row.
module: custom_form
---

## What is stored?
- `rules.shown_when = {"field": <other field's name>, "answers": [...]}` beside `required_when` in `form_fields.rules` (no schema change), cleaned by `pg_cf_rules_clean()` in the same shape (trimmed, one per line, no repeats; no field or no answer = no rule). The other field is found by name without case (`pg_cf_rule_other_field($rules, $fields, 'shown_when')`); gone or renamed, the field is always shown.

## What does the server do? (owner, #5035 question 1)
- `custom_form.php` works out `$shown_when_hidden = pg_cf_shown_when_hidden($fields, $values)` before the checks, from the values `pg_cf_rule_value()` reads (posted value, file name for an upload).
- A hidden field: value set to `''` in the liveform (an upload's `$_FILES` entry emptied), no required/type/rule check, nothing written to the contact (an unanswered question never clears a contact column). Its `form_data` row is stored empty. Submit and save for later alike.
- `pg_cf_rule_verdict(..., $shown)` returns `hidden` when the rule's answers do not hold; answers compare trimmed, without case (`pg_cf_rule_answers_match()`, shared with `required_when`).
- Cascade: a hidden field answers nothing, so a field shown by it is hidden too. Worked out from "all shown" and only ever hiding more, so two fields that show each other cannot flip.
- Decision #2450 (a value condition never hides a required control) stays the widgets' rule; a form field's own `required` yields to `shown_when`.

## And in the browser?
- `pg_cf_apply_field_bindings()` writes `data-pg-shown-when` (the other field's id, its posted name `id` or `id[]`) and `data-pg-shown-answers` (JSON) on every control of the field, boxes of a group and required fields included.
- `pg_cf_validation_script()` hides the field's row (`hidden`) and disables its controls on change/input and at load, so a cached page never shows a stale branch. Row = the largest box around the control holding no control of another field, up to the form or a step `fieldset`; a control placed straight in the form is its own row (its label stays). A control the switch disabled is marked `data-pg-shown-off`; only those are enabled again.
- It runs before the `required_when` switch, which skips disabled controls: a hidden field requires nothing of another. The steps script (`custom-form-steps.md`) already skips disabled controls and `[hidden]` rows.

## Designer?
- Form Field panel row "Shown when" below "Required when": pick list of the other fields ("— Always shown —") + answers textarea; handlers `sd-cf-shown-field` / `sd-cf-shown-answers` write `_cf.rules.shown_when` on the field's lead control through `_cfRulesOf()` / `_cfWriteRules()`.
- Canvas: the lead control's wrapper gets `sd-cf-shown-chip` with "Shown when <field>" (not drawn over a value-condition chip).

## Tests?
- `tests/custom_form_shown_when_test.php`: cleaning, verdict, hidden set with cascade, groups and a cycle, drawn attributes, parity of a rule-less control, script order, submission source, designer row.

Related: docs/memory/designer/custom-form-field-rules.md, docs/memory/designer/custom-form-steps.md
