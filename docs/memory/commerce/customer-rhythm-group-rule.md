---
name: customer-rhythm-group-rule
description: The contact group rule "Rhythm stage" is / is not (H11, RY5): the stage names it takes, the customer_rhythm rows it reads, contacts with two rows or none
module: audiences
---

## What is the rule?
- Condition `rhythm_stage` in `pg_audience_conditions()`, family Orders, label "Rhythm stage", operators is / is not, value kind `stage`. For campaigns and flows to a contact group filled by rules (`core/audiences-rules.md`).
- The value is a stage name of `pg_customer_rhythm_stage_codes()`: regular, due, late, slipping, lost, won_back. "Not yet known" (code 0) is not offered; anything else is dropped by `pg_audience_condition_clean()`.
- The editor (`pg_audience_editor.js`) draws a select from `pg_audience_editor_data()['stages']`, the screen labels of `pg_customer_rhythm_stage_labels()`.

## What does it select?
- `pg_audience_sql()`: `contacts.id IN (SELECT contact_id FROM customer_rhythm WHERE contact_id <> 0 AND stage = ?)` with the stage's code; is not is `NOT IN`, so it also takes contacts with no row (as "Number of orders at most").
- Any row of the contact counts: a contact whose orders went under its e-mail key and its contact key can hold two rows, and the contact card shows only one (`customer-rhythm-report.md`).
- The table is the nightly pass's (`customer-rhythm-store.md`), so the group follows the stages at the next recompute after the pass; a stage moves only forward between orders, so a contact does not leave and re-enter for the same stage.

## Tests?
- `tests/api_customer_rhythm_report_test.php` (clean, SQL, editor list); `tests/audiences_rules_test.php` holds its sample.

Related: docs/memory/core/audiences-rules.md, docs/memory/api/api-customer-rhythm-report.md
