---
name: variant-set-form-template
description: Variant-set product form model: template rows on the product group, copies on each product, form_fields row shapes, pg_pb_apply_form_template rules (2026.4).
---

## What problem and decision?
- Problem: a product form was tied to ONE product via `form_fields.product_id`; a product with nine colour/size combinations meant drawing the same form nine times. (The codebase has 99 `SELECT ... FROM form_fields`, all filtered by `page_id` or `product_id`.)
- Decision: runtime does NOT change. Every product still owns its own rows; catalog / cart / `submit_order.php` are untouched. A template layer sits on top.

## What do the `form_fields` rows look like?
- Template field: `form_type='product_group'`, `product_id` 0, `product_group_id` = group, `template_field_id` 0.
- Copy generated from the template: `form_type='product'`, `product_id` = product, `product_group_id` = group, `template_field_id` = template row id.
- Field added by hand to a variant: `form_type='product'`, product, `product_group_id` 0, `template_field_id` 0.
- Legacy single-product form: same as hand-added (`'product'`, product, 0, 0).
- `form_field_options` is unchanged: it hangs off `form_field_id`, so it also carries the template row's options.

## Why not a separate table?
- `select_field_position()` (`includes/fn/forms.php`) already branches on `product_id` / `page_id`, and the field edit screens carry that split. A third branch is cheaper than rewriting the 24-column schema and all field-type logic a second time.

## How does `pg_pb_apply_form_template($group_id)` (product_builder.php) behave?
- Rule: fields from the template are rewritten; fields hand-added to a variant are kept.
- The second half relies on `template_field_id`: a copy carries the id of its template row, so "delete what I generated last time" is a `WHERE`, not a guess.
- Detecting whether a copy was EDITED is deliberately not done: it would need a hook in `edit_field.php` and field-by-field comparison, and the rule above already answers the question.
- It runs AUTOMATICALLY after every field change, not behind an "apply" button: a drawn-but-unapplied template leaves a set whose products have no form and nothing on screen says so.
- `pg_pb_copy_row()` works by `SELECT *` + dropping identity columns, NOT a written column list: `form_fields` accumulated columns over the years (`rss_field`, `quiz_question`, `contact_field`, `upload_folder_id`...) and a hand-kept list would silently stop copying the next added column.
- `pg_pb_form_template_ready()`: on an install with new code but un-upgraded DB the feature silently disappears; a missing column does not error.

Related: docs/memory/commerce/form-fields-zero-id-query-trap.md
