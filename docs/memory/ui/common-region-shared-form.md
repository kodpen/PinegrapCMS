---
name: common-region-shared-form
description: The common region create and edit screens share includes/forms/common_region_form.php: what it holds, the label fixes, what stays in the screens
---

## Where is the common region form?
- `includes/forms/common_region_form.php` (gate `PG_FUNCTIONS_DIR`). `add_common_region.php` and `edit_common_region.php` stay at the root as thin wrappers and `require_once` it after the designer access check.
- `pg_common_region_form_card($name = '', $content = '', $beside = '', $with_content = true)` prints the Options card: the name between `<cregion>` tags, `$beside` (edit passes its Region Type column), then the Shared Content heading and the rich text textarea (`id="content_textarea"`, content run through `prepare_rich_text_editor_content_for_output()`). The screens still add the WYSIWYG editor code to the head.
- Edit passes `!$component_id` as `$with_content`: a region made a shared component shows no content field and loads no editor.
- Raw inputs, not liveform fields: a save has no error path to refill.

## What does a save share?
- `pg_common_region_form_values($_POST)`: the trimmed name and the content (an array or missing field saves `''`), in INSERT/UPDATE order. The screens run `prepare_rich_text_editor_content_for_input()` on the content; the create and modify log lines name the trimmed name.

## What was fixed on the way?
- The Content and Region Type labels pointed at `for="name"`; they now point at `content_textarea` and `type`.

## What stays in the screens?
- Create: the INSERT, the log line, the notice on the region list.
- Edit: the not-found guard (`tests/panel_id_gate_test.php`), the row load, the body tag line, the Duplicate link, the shared component notice and the convert form (`disable_shortcut`, #2253), the Region Type select, delete, the UPDATE (a converted region keeps content and type), the redirect.
- Tests: `tests/common_region_form_test.php`; `db_q_pages_regions_test.php` and `db_q_regions_styles_menus_test.php` count the screens' queries (the form runs none, counts unchanged).
