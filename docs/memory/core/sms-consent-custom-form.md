---
name: sms-consent-custom-form
description: The SMS marketing question on custom forms (PR #3715): the opt_in_sms binding, which is no contacts column, when it is drawn, how it starts, the sms:marketing row it writes
module: custom_form
---

## What is the binding?
- `opt_in_sms` in `select_contact_field()` ("SMS Opt-In"), so the legacy field screen and the designer (`pg_cf_contact_fields()`) offer it. No `contacts` column holds it: the answer is only a `consent_log` row, channel `sms`, purpose `marketing`.
- Every reader or writer of `contacts.<contact_field>` asks `pg_cf_contact_column()`: the submit's contact update, the prefill in `get_form_info()`, the custom layout screen and `pg_cf_field_initial_value()`, and the member widgets' `contact_fields` (`pg_member_widget_register_opts()`, which `pg_member_register()` / `pg_member_activate()` write). The panel, API and copy paths only carry the binding's name.

## When is it drawn?
- No SMS, no number, no box: outside the editor and edit mode the field is left out unless `pg_cf_sms_question_drawn()`, i.e. `pg_sms_ready()` and a visible field bound to `mobile_phone` (not office use only, not hidden by `?show_<id>=false`, `pg_cf_fields_ask_mobile()`) or the visitor's contact holds a mobile number.
- `pg_cf_drop_sms_question()` removes it in `get_form_info()` and on the custom layout screen; a designed form marks its row `_pg_cf_sms_dropped` and the field is dropped with its label as an office-use-only one is. A generated custom layout wraps the field in `isset($field[<id>])`; a hand-written one draws what it draws.
- `custom_form.php` recomputes the rule from the posted `send_to` and the signed-in contact: a dropped question is neither required nor an answer.

## How does it start?
- With the visitor's contact: `pg_cf_sms_start_value()`, '1' unless the person's last `sms:marketing` row is a refusal, then '0'. Without one: the field's default, as `opt_in` (the boxes stay ticked, #2431).

## When is a row written?
- `pg_cf_sms_answers()`: a visible choice field (`pg_cf_type_has_options()`), not office use only, not hidden through the address, and the contact's `mobile_phone` after the save is a mobile number; ticked = a posted 1 (a check box group: one box posting 1), as the `opt_in` column stores it.
- Passed to the same `pg_consent_log_answers()` call as the `opt_in` answer, source `form`, so a repeated answer writes nothing; never for a contact filled in for someone else or with `connect_to_contact=false`.
- A form without the field writes nothing: its silence is no refusal. The answer rides on the contact update, so a form whose only bound field is `opt_in_sms` writes none.

## Tests?
- `tests/sms_consent_custom_form_test.php`: binding listed, column skip and each reader's use of it, mobile field rule, drawn rule, answers, the single consent log call.

Related: docs/memory/core/sms-consent-box.md, docs/memory/core/consent-log-writers.md
