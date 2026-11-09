---
name: file-picker-search-steps
description: Plan: the file and image pickers that still list every file move to the shared server search (file_picker_search.php, ui/file-picker-search.md); claim as [plan:file-picker-search-steps] <step>
---

## What is done and where does it start?
- The endpoint, its kinds and the Settings pictures, the PEM file and the Add/Edit Short Link file picker search already (`ui/file-picker-search.md`). The steps below reuse `pg_file_picker_field_attributes()` / kind `file`; a new kind is a row of `pg_file_picker_kinds()` with its test in `tests/file_picker_search_test.php`.

## Which steps are left?
- F1 (free, S): the File Manager short link wizard's File field (`view_folders.php` script, `shortLinkOptions.file` from `pg_explorer_handle_short_link_options()` in `includes/explorer/handle_short_links.php`): draw it as a `data-pg-source` combobox (kind `file`) through `pgFields.init()` on the wizard's markup, send only the link's own file (marked when binned) instead of `get_file_options()`, and change `listed(shortLinkOptions.file, item.file_id)`, which asks again when the file is not on the list (`tests/short_link_requests_test.php` pins it).

- F2 (after F1, S): the short link list's quick edit File cell (`pg_short_link_quick_choices()` files in `includes/fn/short_link_list_edit.php`, the list table driver's select cells): server search if the driver's select cell can carry `data-pg-source`, else note why it stays. Once F1 and F2 land, `get_file_options()` has no caller and goes.

- F3 (decision first, M): a "Choose from File Manager" button beside a picker. The explorer has no pick mode today (no way to open it in a dialog, filtered to a kind, and hand a name or id back), so this needs one in `view_folders.php` and `explorer_side.js`; worth it only if browsing by folder or thumbnail is wanted beyond the name search.

- F4 (optional, S): `select_image_options()` (`includes/fn/forms.php`), the image table of the product and form image dialogs, still prints every image on each load; it is a table, not a select, so it would need its own search box over a `image` kind.
