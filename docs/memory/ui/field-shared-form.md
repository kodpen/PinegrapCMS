---
name: field-shared-form
description: The form field create and edit screens share includes/forms/field_form.php
---

## Where is the form field form?
- `includes/forms/field_form.php` (gate `PG_FUNCTIONS_DIR`), PR #2364 (create) and #2464 (edit). `add_field.php` and `edit_field.php` stay at the root as thin wrappers and `require_once` it.
- `pg_field_form_cards($liveform, $field, $context)` prints the field type, settings, information and choices cards. `$field` is `pg_field_form_new_field($folder_id)` for a new field (required and "Include in translation" on, the form's folder as upload folder) or the stored row with `options` from `pg_field_form_options_text()` (one `pg_field_form_option_line()` per choice, the format `…_parse_option_line()` reads back; escaped once by the cards). `$context`: `form_type`, `quiz`, `position`, `form_id`, `page_type`, `upload_folder_row` (add: custom forms only; edit: every form, as before).
- A new field's name gets `required`; a stored one gets its value.

## Which helpers do the screens share?
- Form context: `pg_field_form_page_form_type()` (page type → custom/shipping/billing, express order by its form type), `…_type_name()`, `…_filter()` (form_type is pinned for variant sets and express order), `…_page_form_name()` (form name + quiz), `…_product_form_name()`, `…_express_query()` (`&form_type=` of the redirect).
- Save: `…_validate_name()` (blank, `^ & [ ] < > /`), `…_required_value()` (information is never required), `…_save_options()` (choices with e-mails, triggers and upload folders; `$option_columns` are the form id columns of each choice row: add writes its own id column, edit writes `page_id` and `product_id`; edit deletes the old choices and targets first), `…_update_sort_order()`, `…_touch_owner()` (product or page last modified; a variant set template has neither), `…_layout_warning()` (the "edit the custom layout" warning, kept for classic custom-layout pages), `…_saved_notice()`.
- Pure helpers are tested in `tests/field_form_test.php`; `e()` needs a connection, so `…_filter()` is not.

## What stays in add_field.php?
- `pg_request_single_values()`, the page / variant set / product branches with their access checks and breadcrumbs, the position default (below the last field), the hidden `form_type` input, the Create button, the INSERT of the field row, `pg_pb_apply_form_template()` for a variant set, both redirects.

## What stays in edit_field.php?
- The not-found guard, the field row read, the read-only view of a designer form's field (a POST is refused), the page / variant set / product branches (product branches set `$page_type = ''`), the position (the field above), the delete with its submitted data and files, the folder check that allows the old upload folder, the UPDATE, the pretty URL refresh when the RSS field becomes title, both redirects.

## What does a refused save keep?
- A refused name (blank, or `^ & [ ] < > /`) stores the post (`add_fields_to_session()`) before the redirect. The screen reads it back with `pg_field_form_session_values()` (null when the form was not sent back, or was sent back for another field id) and lays it over the new or stored field with `pg_field_form_posted_field()` (a check box missing from the post is off; tested). Both screens call `remove_form()` once drawn; edit also drops any other entry before drawing.
- Symptom fixed: Create Field came back empty, Edit Field came back with the stored field, and the left-over `name` error entry of the shared `edit_field` liveform emptied the Name box of every later Edit Field screen in the session.
