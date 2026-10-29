---
name: page-form-refused-save
description: Create Page and Edit Page come back with what was typed when a save is refused: the stored post, the redraw helpers in includes/forms/page_form.php, what is not redrawn
---

## Which refusals keep what was typed?
- Every one: blank name, name in use, `/` in a catalog or catalog detail name, a custom form that cannot run (name taken, Next Page confirmation with no page), on edit the canonical address or snippet length. Each calls `add_fields_to_session()` before the redirect; `pg_page_form_refuse()` does it itself.
- Before, Create came back empty on the Standard type with every custom form setting gone, and Edit came back with the stored page; only the canonical address and snippet length were kept.

## How does the screen draw from the stored copy?
- `pg_page_form_posted($liveform[, $page_id])`: field => value, empty until a refusal (`prefill()`), and on edit when the copy belongs to another page.
- `pg_page_form_redraw_fields($current, $posted, $check_boxes)`: the typed value of each of the screen's own fields; a listed check box missing from the copy is `'0'`, so list only boxes the form printed (home for role < 3, noindex/nofollow and noarchive only where their columns exist).
- `pg_page_form_redraw_type($posted, $current, $user)`: the type chosen, when known and the user may set it.
- `pg_page_form_redraw_type_properties($properties, $type, $posted)`: that type's settings through `pg_page_form_type_properties_from_post($type, 0, $posted)` (third parameter is the source, `$_POST` by default), plus the custom form offer when its rows were printed; the share image field as chosen, without the save's query.

## What is not redrawn?
- The calendars and form list views a page links to (link tables, read by page id).
- On edit the heading, sub-navigation, button bar and the rename box's old name describe the stored page.
- Create calls `remove_form()` after drawing (it used to only unmark errors), so typed values do not come back on the next visit. No query was added: the counts in `db_q_pages_regions_test.php` and `db_q_user_page_screens_test.php` hold.
- Test: `tests/page_form_redraw_test.php`.

Related: docs/memory/ui/page-shared-form.md, docs/memory/ui/liveform-notices-trap.md
