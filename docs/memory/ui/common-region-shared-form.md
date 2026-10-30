---
name: common-region-shared-form
description: The common region create and edit screens share includes/forms/common_region_form.php: what it holds, the label fixes, what stays in the screens
---

## Where is the common region form?
- `includes/forms/common_region_form.php` (gate `PG_FUNCTIONS_DIR`). `add_common_region.php` and `edit_common_region.php` stay at the root as thin wrappers and `require_once` it after the designer access check.
- `pg_common_region_form_card($name = '', $content = '', $beside = '', $with_content = true)` prints the Options card: the name between `<cregion>` tags, `$beside` (edit passes its Region Type column), then the Shared Content heading and the rich text textarea (`id="content_textarea"`, content run through `prepare_rich_text_editor_content_for_output()`). The screens still add the WYSIWYG editor code to the head.
- Edit passes `!$component_id` as `$with_content`: a region made a shared component shows no content field and loads no editor.
- Raw inputs, not liveform fields; the one error path is a taken name (below), refilled from the screen's liveform session.
- `$name_taken` (last argument) marks `#name` `is-invalid` with `aria-describedby="name_error"` and prints the existing key 'The name that you entered is already in use, so please enter a different name.' under it (`pg_region_form_name_error*()`, shared by the designer and dynamic forms, which `require_once` this file).

## Why is a taken name refused, and how?
- `<cregion>` reads one row per name (lowest `cregion_id`, `pg_common_regions_from_rows()`) and panel lookups take `LIMIT 1`: a second region with the same name was saved with a success notice yet never shown. Common and designer regions share the cregion table and the tag, so one name space.
- `pg_cregion_name_taken($name, $id = null, $lookup = null)`: no check for `''`; create counts `cregion_name = ?` (SQL '=' under the column collation); edit first reads the stored name and checks only when the posted name differs (strict), leaving its own id out, so a site that already holds duplicates still saves. `$lookup($sql, $values)` returns the first column (default `pg_region_form_lookup()`, db_q); tests stub it.
- On refusal the screen calls `add_fields_to_session(array('trim' => false))` (content kept as typed), `mark_error('name', …)`, and redirects to itself; the GET draws the posted name, content and region type and calls `remove_form()`. Tests: `tests/region_name_taken_test.php`.

## What does a save share?
- `pg_common_region_form_values($_POST)`: the trimmed name and the content (an array or missing field saves `''`), in INSERT/UPDATE order. The screens run `prepare_rich_text_editor_content_for_input()` on the content; the create and modify log lines name the trimmed name.

## What was fixed on the way?
- The Content and Region Type labels pointed at `for="name"`; they now point at `content_textarea` and `type`.

## What stays in the screens?
- Create: the name check, the INSERT, the log line, the notice on the region list.
- Edit: the not-found guard (`tests/panel_id_gate_test.php`), the row load, the body tag line, the Duplicate link, the shared component notice and the convert form (`disable_shortcut`, #2253), the Region Type select, delete, the name check on a rename, the UPDATE (a converted region keeps content and type), the redirect.
- Tests: `tests/common_region_form_test.php`; `db_q_pages_regions_test.php` and `db_q_regions_styles_menus_test.php` count the screens' queries (the form runs none, counts unchanged). Done in #2843.
