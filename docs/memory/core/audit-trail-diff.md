---
name: audit-trail-diff
description: How pg_audit_diff() decides a field changed: values as strings, NULL and '' equal when both sides hold the field
module: audit
---

## How are the two sides compared?
- `pg_audit_diff()` (`includes/fn/audit.php`) normalises each value with `pg_audit_value()` (scalars as strings, `true`/`false` as `'1'`/`'0'`, arrays recursively) and keeps a field when the two differ (`!==`). `5` and `'5'` are no change.
- A field only one side has is listed with `null` on the other; `pg_audit_row()` then empties the side not given (before a create, after a delete).

## Are NULL and '' a change?
- No, when both sides hold the field: such a field is skipped (PR #4282). `0` and `'0'` are values, still compared.
- Why: save paths differ in how they leave an empty text column. Import Contacts lists only its CSV columns in the INSERT, so the rest stay NULL; Edit Contact writes every column, empty ones as ''. The first Edit Contact save of an imported contact logged ~25 fields `— → empty` beside the one really changed (#4232); a first save after an upgrade did the same for new nullable columns.
- Import still writes NULL; nothing reads the difference, so it was left alone.
- A field one side lacks still counts even if the other is '' (a create keeps its empty fields).
- Nested arrays (JSON text) are compared as they are; only top-level fields get the rule.

## Tests?
- `tests/audit_log_test.php`: `test_audit_diff_keeps_changed_fields_only`, `test_audit_diff_null_and_empty_string_are_equal` (no line for a save that only turns NULL into '').

Related: docs/memory/core/audit-trail-writers.md, docs/memory/core/audit-trail-stored-values.md, docs/memory/core/audit-trail-contacts.md
