---
name: custom-form-field-rules
description: Visitor rules of a designed form field (form_fields.rules, step 11.0.135, PR #3737): "Not before today" and "Required when", where they are written and checked, the staff exemption, Cafe and Clinic.
module: custom_form
---

## What is stored?
- `form_fields.rules TEXT NULL` (step 11.0.135, decision #3669): a JSON object, NULL = no rule. Keys: `min_date` = `"today"` (a day picker only; a fixed date may come later without a migration) and `required_when` = `{"field": <other field's name>, "answers": [...]}`.
- The other field is named, never its row id (a re-sync may renumber it), and found at check time without case (`pg_cf_rule_other_field()`). Gone or renamed: the rule asks nothing.
- `pg_cf_rules_clean($rules, $type)` is the one shape (drops unknown keys, `min_date` off a date, empty answers; answers may be a list or text one per line); `pg_cf_rules_json()` gives the column value or null.

## Where is it written?
- The designer keeps it on the field's first control as `props._cf.rules` (`_cfFieldLeadOf()`, the control `_pg_cf_controls()` reads). `_pg_cf_controls()` cleans it with the type (a month/week control counts as not a date), and `pg_cf_sync_page_form()` writes `$set['rules']` (NULL when none, only while the column exists).
- Designer rows in the Form Field panel: "Not before today" (switch, `<input type="date">` only) and "Required when" (pick list of the other fields + answers textarea, one per line). Helpers `_cfRulesOf()`, `_cfWriteRules()` (an empty `_cf` is removed: PHP round-trip trap).

## Where is it checked?
- Site submissions only: `custom_form.php` selects `rules` (probed with `pg_schema_has()`) and calls `pg_cf_check_rules($liveform, $field, $fields, $action == 'submit')` after the type, pattern and collection checks.
- `pg_cf_rule_verdict($rules, $value, $other, $today, $submit)` (pure): `min_date` refuses an ISO date before `$today` whenever given (saved for later too); an empty value passes. `required_when` holds only on submit, when the other field's value (or one of its ticked values) equals an answer, trimmed and without case.
- Today is `pg_cf_rules_today()` = `date('Y-m-d')` in the default zone init.php sets from `TIMEZONE`.
- Message: the field's own `validation_message`, else "{var:1} cannot be before today." / "{var:1} is required.".
- Staff paths are exempt: `submitted_form_form.php`, `pg_coll_check_field()`, API and MCP never call it, so staff can record yesterday's booking. A test guards this.

## And in the browser?
- `pg_cf_apply_field_bindings()`: a day picker with `min_date` gets `min` = today; a field with `required_when` (not always required, not a box of a group, not rich text) gets `data-pg-required-when` = the other field's id (its posted name, `id` or `id[]`) and `data-pg-required-answers` (JSON).
- `pg_cf_validation_script()` switches `required` on change/input. It hides nothing, so the widgets decision #2450 (a value condition never hides a required control) is untouched.
- A cached page may carry yesterday's `min`; the server refuses anyway.

## Templates?
- Cafe: reservation date (`cf-res-date`) and catering date (`cf-quote-date`) `min_date`; the catering address `required_when` delivery = `lang('Delivered to us')` (the radio's own value). Clinic: preferred date (`cl-appt-date`). Set at install; installed sites get no data step (the operator turns them on in the designer). Fixes #3572 items 1, 3, 4.

## Tests?
- `tests/custom_form_rules_test.php`: cleaning, JSON, today boundary and zone, several answers, missing/renamed field, the migration step, derivation from controls, sync and site check sources, staff exemption, browser side, templates.

Related: docs/memory/designer/custom-form-sync.md, docs/memory/designer/custom-form-render.md, docs/memory/workflow/decisions-made-schema-6.md
