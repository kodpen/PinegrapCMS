---
name: designer-region-shared-form
description: The designer region create and edit screens share includes/forms/designer_region_form.php: what it holds, the label fix, what stays in the screens
---

## Where is the designer region form?
- `includes/forms/designer_region_form.php` (gate `PG_FUNCTIONS_DIR`). `add_designer_region.php` and `edit_designer_region.php` stay at the root as thin wrappers and `require_once` it after the designer access check.
- `pg_designer_region_form_cards($name, $content, $form)` prints the wide layout (`edit-screen-wide-layout.md`, step B11): the form column (Options card: the name between `<cregion>` tags and, when `$form['type']` is given (edit), the Region Type select; a Shared Content card with the HTML Code Snippet textarea, `id="code"`, code editor) and the sticky Summary (the body tag and the region type, both following the fields as typed, plus `$form['status']` rows). The body tag left the edit toolbar.
- The row, the sticky save bar, the Summary row helper and the type labels are the common region form's (`pg_common_region_form_layout()`, `pg_common_region_form_status_row()`, `pg_common_region_form_type_labels()`, `common-region-shared-form.md`). Edit adds a Last Modified row (time and user, from a `LEFT JOIN user` on the row load).
- The screens print raw inputs, not liveform fields; a refused name (blank, over 100 characters or taken, `pg_region_form_name_refusal()`) is the one error path: `$form['name_error']` marks the required name field, and the posted values come back from the screen's liveform session.
- A taken name is refused with `pg_cregion_name_taken()` from `common-region-shared-form.md` (same table, same `<cregion>` tag, same rules); this form `require_once`s the common form for it.

## What does a save share?
- `pg_designer_region_form_values($_POST)`: the trimmed name and the content (an array or missing field saves `''`), in INSERT/UPDATE order. The screens append the type, user and id themselves; the activity log names the trimmed name.

## What was fixed on the way?
- Every label pointed at `for="name"`: the code snippet label now points at the textarea (`code`), the Region Type label at its select (`type`).

## What stays in the screens?
- Create: the name check, the INSERT and the notice on the region list.
- Edit: the not-found guard (`tests/panel_id_gate_test.php`), the row load, the Last Modified row, the Duplicate link, delete, the name check on a rename (the refusal comes back with `send_to`), the UPDATE and the `send_to` redirect.
- Tests: `tests/designer_region_form_test.php`; `db_q_pages_regions_test.php` counts the screens' queries (the form runs none).
