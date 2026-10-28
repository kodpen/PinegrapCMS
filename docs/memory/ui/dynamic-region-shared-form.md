---
name: dynamic-region-shared-form
description: The dynamic region create and edit screens share includes/forms/dynamic_region_form.php: what it holds, the label fix, what stays in the screens
---

## Where is the dynamic region form?
- `includes/forms/dynamic_region_form.php` (gate `PG_FUNCTIONS_DIR`). `add_dynamic_region.php` and `edit_dynamic_region.php` stay at the root as thin wrappers and `require_once` it right after `validate_area_access($user, 'administrator')`: the PHP code field is administrator-only, the same right as the designer's code tab (`tests/designer_dynamic_region_test.php` looks for that line in the edit screen).
- `pg_dynamic_region_form_card($name = '', $code = '')` prints the Options card: the name between `<dregion>` tags, the heading and the PHP Code Snippet textarea (`id="code"`, `pg_code_editor_attach()` with `code_type` `php`).
- Raw inputs, not liveform fields: a save has no error path to refill.

## What does a save share?
- `pg_dynamic_region_form_values($_POST)`: the trimmed name and the code (an array or missing field saves `''`), in INSERT/UPDATE order. The screens append the user and the id; the create and modify log lines name the trimmed name.

## What was fixed on the way?
- The code snippet label pointed at `for="name"`; it now points at the textarea (`code`).

## What stays in the screens?
- Create: the INSERT, the log line, the notice on the region list.
- Edit: the not-found guard (`tests/panel_id_gate_test.php`), the row load, the body tag line, delete, the UPDATE, the redirect.
- Tests: `tests/dynamic_region_form_test.php`; `db_q_pages_regions_test.php` counts the screens' queries (the form runs none). Done in #2822.
