---
name: filter-on-form-data-rows
description: A filter on a submitted form's answers is an EXISTS condition, never a join: a check box group keeps one form_data row per ticked box.
module: submitted_form_list
---

## How is a list of submitted forms filtered by an answer?
- With `EXISTS (SELECT 1 FROM form_data WHERE form_id = forms.id AND form_field_id = ? AND data LIKE ?)` (`pg_submitted_form_list_field_condition()`), one condition per field, the fields of one form ANDed and the forms ORed.
- Never with a `LEFT JOIN form_data`: the list query has no GROUP BY, and a field stored in several rows (a check box group, a multiple pick list) matched once per row.

## What did the join do before #5143?
- The Submitted Forms advanced filter joined each filtered field's rows. A text that two ticked boxes of one form both held (filter "e" on "red" and "blue") listed that form twice, counted it twice in the totals, and wrote it twice into the CSV export (`view_submitted_forms.php` reads the same FROM and WHERE).
