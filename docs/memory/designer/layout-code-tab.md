---
name: layout-code-tab
description: A classic page's custom layout (data/layouts/<id>.php) as a code tab of the visual designer: who may edit it, the layout_code endpoint, regenerate, unmodified on save, Ctrl+G, hosted sites
module: layouts
---

## What opens it?
- The Region panel of a `system` node on a page that draws a custom layout (layout type `custom`, a page type `check_if_page_type_supports_layout()` lists): "Edit layout (PHP)" opens a file tab `layout:<page_id>` (language `php`); "Regenerate layout" puts `generate_layout_content()` in the tab, unsaved (`_sdLayoutCodeOpen()`, `_sdLayoutPropsHtml()` in `style_designer.js`).
- `_design.layoutPages` (`includes/designer_screen.php`) lists the design's pages that qualify (`pg_layout_custom_page_ids()`); the active tab page decides.

## Where does Ctrl+G land? (#1881)
- On such a page of a design, the toolbar's `page_designer_button` ("Open Style Designer", `pg_page_actions()`) adds `&layout=1` (`pg_layout_tab_param()`: role 0-1, not hosted); the classic page designer used to open on the layout there. "Edit Page Style" still opens the canvas, so both are listed.
- `_design.openLayout` (`pg_layout_open_page_id()`): the landed page only, when it is in `layoutPages`; the editor drops `layout` from the address and calls `_sdLayoutCodeOpen()`, so a tree that lost its `system` node still reaches the layout.
- Every page whose custom layout is drawn qualifies: a `layout_modified` page with layout type `system` draws the system template; a standard page never calls `render_layout()`.

## Who may edit it?
- Role 1 or better (designers and administrators), the right of the classic layout editor (`page_designer.php?type=layout`: `validate_area_access($user, 'designer')`; `get_layout`/`update_layout` sit behind the general gate). Wider than the dynamic region tab (administrators only, `dynamic-region-in-designer.md`), on purpose: the classic rule is kept, never widened.
- `_design.layoutCode` shows the buttons; api.php action `layout_code` (`includes/panel/designer.php`), not exempt, then `validate_token()` and a role <= 1 check before any query; refuses a page without a custom layout.

## Where do read and save live?
- `includes/fn/layouts.php` (loaded where needed, not by the manifest): `pg_layout_read()`, `pg_layout_save()`, `pg_layout_generated()`, `pg_layout_same_as_generated()`, `pg_layout_file_name()`. The classic `get_layout`/`update_layout` handlers call the same functions.
- The file is `LAYOUT_DIRECTORY_PATH/<int page id>.php`; `pg_layout_file_name()` accepts only a positive integer, so no request value reaches the path.
- Save: a text equal to the generated one with white space removed deletes the file and sets `page.layout_modified = 0` (the layout then follows the form's fields again); anything else writes the file and sets 1. Logged as "layout for page (name) was modified". Page cache: `save` dirty, `read`/`regenerate` clean.

## Hosted sites?
- `pg_hosted()`: Ctrl+G opens the canvas; the tab is read only (`_design.layoutReadOnly`), no Regenerate, save refused with "Custom layouts are not available on this site."; read shows the generated layout, which is what `render_layout()` draws there (the classic `get_layout` now shows the same).

## What stays?
- The field-change warning (`add_field.php`, `edit_field.php`, `delete_fields.php`) for a modified custom layout.

## Tests?
- `tests/designer_layout_retirement_test.php`: the Ctrl+G parameter and the page opened on load.
- `tests/designer_layout_code_test.php`: file names, the comparison, the action row, the handler's check order, the screen flags, the save path, page cache lists.
