---
name: picker-deleted-values
description: A page or file id with no row left: last name from audit_log or "Deleted page"/"Deleted file", id in the meta line; deleting a page clears start pages; Users › Start Page cell (#9386)
module: picker
---

## What does a picker field show for a deleted page or file?
- A kind storing an id (`page` kinds, `file`, `channel_file`, `canvas_*`) never shows `#42` as the name. `pg_picker_field_view()` shows the last name the site kept, else "Deleted page" / "Deleted file"; the meta line reads "No longer exists · #42"; the amber note stays. A kind storing a name keeps the name as stored.
- The last name is the newest non-blank `audit_log.label` of the id's `page` / `file` lines (`pg_picker_last_names()`, read in `pg_picker_resolve_answer()` for the ids with no row). A Recycle Bin parked name `pgbin_<id>_name` is told as `name` (`pg_picker_last_names_from_rows()`, pure). A site without the trail gets the plain label.
- The value is still posted unchanged until another is chosen; the saves keep their checks.

## Can a page that exists show as deleted?
- No. `pg_picker_resolve_answer()` reads the page by id with LEFT JOINs only; a failed query is fatal (`pg_db_run()`), never a silent "missing". The `#294` of #9386 was a page deleted for good while it was a start page.

## Why does deleting a page clear start pages?
- `user.user_home` kept the id of a page deleted from Edit Page, the pages list or a Recycle Bin purge (`pg_delete_page_record()`, the API too). Each now runs `pg_page_start_page_forget()` (`includes/fn/page_home.php`): `user_home = 0` for that page, as "[None]". The design template install already moved `user_home` to its new start page.
- Binning a page keeps it: a binned page still resolves, shown under its name with the red note.

## What does Users › Start Page show?
- `pg_user_list_start_page_cell()` (`includes/fn/user_list.php`, pure): the page name, nothing for 0, a muted italic "Deleted page" with the id in its title for an older dangling id.
- Its quick edit cell stays closed for such a row (the stored page is not among the choices).

## What do the other screens show for a gone id?
- Designer page pickers (`_swMissingPageOption()`): "Loading…" until the full page list arrives, then "Deleted page" for an id with no page; a binned page keeps its name with "(deleted)". A page with no name is "Untitled page", never `#id`.
- Workspace scheduled action summary: "E-mail a deleted page to …". Record revision history: a gone file is "Deleted file".

## What is left for a follow-up?
- `#id` fallbacks for a nameless record: designer form labels (`_swFormLabels`), shared component usage (`_sharedUsageText`, `designer_screen.php` usage cell), Workspace proposal header (`workspace.js`), File Manager page and file usage rows.
- `select_page()` lists (picker fields off, product builder): a stored id that is gone has no option, so a save writes the empty one.

Tests: `tests/picker_test.php`, `tests/page_home_test.php`, `tests/user_list_test.php`, `tests/page_form_page_fields_test.php`.
