---
name: custom-style-shared-form
description: The custom page style create and edit screens share includes/forms/custom_style_form.php: what it holds, the defaults, what stays in the screens
---

## Where is the custom page style form?
- `includes/forms/custom_style_form.php` (gate `PG_FUNCTIONS_DIR`). `add_custom_style.php` and `edit_custom_style.php` stay at the root as thin wrappers and `require_once` it.
- `pg_custom_style_form_cards($style, $social_networking, $name_error, $status_rows)` prints the two columns of the wide layout (`ui/edit-screen-wide-layout.md`, B11): the form column with Page Style Options (name, code editor), the sticky status column with Additional Options (collection, layout type override, the social networking position list only while `SOCIAL_NETWORKING` is on) and, when `$status_rows` is given (edit, `pg_custom_style_form_edit_status_rows()`), a Summary of the folders and pages using the style and Last Modified. `pg_custom_style_form_layout($cards, $buttons)` wraps them with the sticky save bar.
- `$style` is the stored `style` row on edit; create passes `pg_custom_style_form_defaults()`: empty name, a page skeleton with `<system>` between two `<pregion>`s, collection A, no override, bottom left.
- Collection B is checked only for a stored `b`; an empty collection (left by an old save) shows A. The layout override list's first option reads 'No override (the page type layout)'.
- `view_styles.php` prints the Layout Type column through `pg_style_layout_type_label()` (System / Custom translated, '' for none), not `ucwords()` of the enum.

## What does a save share?
- `pg_custom_style_form_values($_POST, $social_networking)`: column => value in INSERT/UPDATE order (`style_name` trimmed, `style_code`, `social_networking_position` only while social networking is on, `collection`, `layout_type`). Each pick list value goes through `pg_custom_style_form_choice()` against `pg_custom_style_form_choices()`: outside the list it falls back to a / '' / bottom_left, since the non-strict session stored an unknown enum as `''`. Both screens bind them through `db_q()`; the edit UPDATE was a raw `mysqli_query()` before.

## What stays in the screens?
- Create: the INSERT, the notice on the style list.
- Edit: the not-found guard, the record load, heading, the name and Duplicate link in the toolbar (Last Modified moved to the Summary), the folder/page use count that turns delete into a refusal, the Recycle Bin or hard delete, the UPDATE, Save / Save & Return and `send_to`.
- Tests: `tests/custom_style_form_test.php`; `db_q_contacts_forms_test.php` and `db_q_pages_regions_test.php` count the screens' `db_q()` calls.

## Can a custom page style be saved without a name?
- No. Create (before the INSERT) and the edit save branch (before the UPDATE, never Delete) refuse an empty trimmed `style_name` with 'Name is required.' and Go back. A name of only spaces passes the browser's `required` and was stored blank, an empty option in the folder and page style pickers.
- Edit's Delete submit carries `formnovalidate`, so a cleared Name box does not block it.

## Which names are refused?
- Beside a blank name (above), `pg_region_form_name_refusal($name, pg_style_name_taken($name, $id))` from the region form: over 100 characters (the column cut it silently) or held by another style of any kind. The visual designer refuses to save a design whose name another style holds, so a custom style named like a design blocked both.
- `pg_style_name_taken()` (lookup stubbed in tests) skips '' and, on edit, an unchanged name; it leaves its own id out.
- A refusal stores the post in the screen's liveform (`add_custom_style` / `edit_custom_style`), marks `name` and redirects; the GET refills the form and prints the text under the name input.
- Duplicate cuts a 100-character name before its `[n]` (`pg_unique_name_limit('style')`): the column cut the suffix and the copy kept its source's name.

- The edit screen's style-kind guard, View Source's way back and the bulk delete read: `page-style-screen-guards.md`.
