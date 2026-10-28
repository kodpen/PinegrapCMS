---
name: folder-shared-form
description: The folder create and edit screens share includes/forms/folder_form.php: what it holds, the inputs that differ, what stays in the screens
---

## Where is the folder form?
- `includes/forms/folder_form.php` (gate `PG_FUNCTIONS_DIR`), loaded by `add_folder.php` and `edit_folder.php` with `require_once` after `init.php`. Done in #2894.
- `pg_folder_form($values)` fills the option lists (`select_folder()`, `select_access_control_type()`, for role < 3 the `select_style()` / `get_mobile_style_options()` selects) and `get_token_field()`, then calls `pg_folder_form_markup($values)`.
- `pg_folder_form_markup()` reads no database (the pure test renders it): name and Sorting stepper row, Archive switch, Content Options card (Parent Folder, Folder Access Control Type), desktop/mobile style row, hidden `send_to`, the token.

## What differs between the two calls?
- Name: create passes the liveform `output_field()` as `name_field`; edit passes `name` and gets a plain input with the stored value.
- Parent: create passes `?id` as `parent`; edit passes `show_parent => false` for the root folder and `exclude => $folder_id` so the folder and its subtree are not offered.
- Styles for role ≥ 3: edit queries the stored or inherited style names itself and passes them as `style_label` / `mobile_style_label` (escaped); create keeps the default "Default (inherit)".
- Titles: `edit => true` gives "Folder Information", else "New Folder Information".

## What does a save share?
- `pg_folder_form_values($_POST, $role)`: trimmed name, order, access control type, archived flag, and the style ids (empty list for role ≥ 3). A missing or non-scalar value is `''`.

## What stays in the screens?
- Create: the parent edit-access check, the parent level, the blank-name error, the INSERT, the log line, the redirect.
- Edit: the not-found guard, the row load, Duplicate, Delete with the child check, the parent-move cycle walk and `change_level()`, the UPDATE, the log line, the redirect.
- Every query stays in the screens (`db_q_content_screens_test.php`, `db_q_pages_regions_test.php` count them). Tests: `tests/folder_form_test.php`; `panel_steppers_names_test.php` counts the one shared stepper pair.
