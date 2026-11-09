---
name: dynamic-region-shared-form
description: The dynamic region create and edit screens share includes/forms/dynamic_region_form.php: what it holds, the label fix, what stays in the screens
---

## Where is the dynamic region form?
- `includes/forms/dynamic_region_form.php` (gate `PG_FUNCTIONS_DIR`). `add_dynamic_region.php` and `edit_dynamic_region.php` stay at the root as thin wrappers and `require_once` it right after `validate_area_access($user, 'administrator')`: the PHP code field is administrator-only, the same right as the designer's code tab (`tests/designer_dynamic_region_test.php` looks for that line in the edit screen).
- `pg_dynamic_region_form_cards($name = '', $code = '', $form = array())` prints the product_builder layout (`edit-screen-wide-layout.md`): a form column with the Options card (the name between `<dregion>` tags) and the PHP Code card (the PHP Code Snippet textarea, `id="code"`, `pg_code_editor_attach()` with `code_type` `php`), then a sticky status column with a Summary of the body tag (`#dregion_summary_tag`, follows the name as it is typed) and `$form['status']` rows. The screens wrap it in `pg_common_region_form_layout()` for the sticky save bar.
- Edit adds a Last Modified row (`pg_common_region_form_status_row()`, the row's time and user); the body tag left the toolbar for the Summary, so the edit screen has no toolbar. Redesigned in B11 (`edit-screen-wide-layout.md`).
- Raw inputs, not liveform fields; a refused name (blank, over 100 characters or taken, `pg_region_form_name_refusal()`) is the one error path: `$form['name_error']` marks the required name field, and the posted values come back from the screen's liveform session.
- `pg_dregion_name_taken($name, $id = null, $lookup = null)`: `<dregion>` evals only the first row of a name, so another region's name is refused; same rules as `pg_cregion_name_taken()` (`common-region-shared-form.md`, whose file this form `require_once`s for the error markup, the lookup, the save bar layout and the Summary row).

## What does a save share?
- `pg_dynamic_region_form_values($_POST)`: the trimmed name and the code (an array or missing field saves `''`), in INSERT/UPDATE order. The screens append the user and the id; the create and modify log lines name the trimmed name.

## What was fixed on the way?
- The code snippet label pointed at `for="name"`; it now points at the textarea (`code`).

## What stays in the screens?
- Create: the name check, the INSERT, the log line, the notice on the region list.
- Edit: the not-found guard (`tests/panel_id_gate_test.php`), the row load (with the last change and its user), the Last Modified row, delete, the name check on a rename, the UPDATE, the redirect.
- Tests: `tests/dynamic_region_form_test.php`; `db_q_pages_regions_test.php` counts the screens' queries (the form runs none). Done in #2822.
