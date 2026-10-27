---
name: field-shared-form
description: The form field screens share includes/forms/field_form.php; add_field.php uses it, edit_field.php is the next step
---

## Where is the form field form?
- `includes/forms/field_form.php` (gate `PG_FUNCTIONS_DIR`), PR #2364. `add_field.php` stays at the root as a thin wrapper and `require_once`s it; `edit_field.php` still has its own copy and moves onto it in the next step (plan `refactor-candidates` item 2).
- `pg_field_form_cards($liveform, $field, $context)` prints the field type, settings, information and choices cards. `$field` is `pg_field_form_new_field($folder_id)` for a new field (required and "Include in translation" on, the form's folder as upload folder) or the stored row with `options` as the choices text (escaped once by the cards). `$context`: `form_type`, `quiz`, `position`, `form_id`, `page_type`, `upload_folder_row` (add: custom forms only; edit printed it for every form).
- A new field's name gets `required`; a stored one gets its value.

## Which helpers do the screens share?
- Form context: `pg_field_form_page_form_type()` (page type → custom/shipping/billing, express order by its form type), `…_type_name()`, `…_filter()` (form_type is pinned for variant sets and express order), `…_page_form_name()` (form name + quiz), `…_product_form_name()`, `…_express_query()` (`&form_type=` of the redirect).
- Save: `…_validate_name()` (blank, `^ & [ ] < > /`), `…_required_value()` (information is never required), `…_save_options()` (choices with e-mails, triggers and upload folders; `$option_columns` are the form id columns of each choice row), `…_update_sort_order()`, `…_touch_owner()` (product or page last modified; a variant set template has neither), `…_layout_warning()` (the "edit the custom layout" warning, kept for classic custom-layout pages), `…_saved_notice()`.
- Pure helpers are tested in `tests/field_form_test.php`; `e()` needs a connection, so `…_filter()` is not.

## What stays in add_field.php?
- `pg_request_single_values()`, the page / variant set / product branches with their access checks and breadcrumbs, the position default (below the last field), the hidden `form_type` input, the Create button, the INSERT of the field row, `pg_pb_apply_form_template()` for a variant set, both redirects.
