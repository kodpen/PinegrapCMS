---
name: custom-style-shared-form
description: The custom page style create and edit screens share includes/forms/custom_style_form.php: what it holds, the defaults, what stays in the screens
---

## Where is the custom page style form?
- `includes/forms/custom_style_form.php` (gate `PG_FUNCTIONS_DIR`). `add_custom_style.php` and `edit_custom_style.php` stay at the root as thin wrappers and `require_once` it.
- `pg_custom_style_form_cards($style, $social_networking)` prints the Page Style Options card (name, code editor) and the Additional Options card (collection, layout type override, the social networking position list only while `SOCIAL_NETWORKING` is on).
- `$style` is the stored `style` row on edit; create passes `pg_custom_style_form_defaults()`: empty name, a page skeleton with `<system>` between two `<pregion>`s, collection A, no override, bottom left.
- Collection B is checked for any value but `a`, as before.

## What does a save share?
- `pg_custom_style_form_values($_POST, $social_networking)`: column => value in INSERT/UPDATE order (`style_name` trimmed, `style_code`, `social_networking_position` only while social networking is on, `collection`, `layout_type`). Both screens bind them through `db_q()`; the edit UPDATE was a raw `mysqli_query()` before.

## What stays in the screens?
- Create: the INSERT, the notice on the style list.
- Edit: the not-found guard, the record load, heading, last-modified line and Duplicate link, the folder/page use count that turns delete into a refusal, the Recycle Bin or hard delete, the UPDATE, Save / Save & Return and `send_to`.
- Tests: `tests/custom_style_form_test.php`; `db_q_contacts_forms_test.php` and `db_q_pages_regions_test.php` count the screens' `db_q()` calls.
