---
name: list-table-quick-edit-pages
description: Pages list quick edit (includes/fn/list_table_edit.php): which cells are fields for whom, where the choices come from, how name, folder, style, type, form fields and switches are written
module: list_table_edit
---

## Which cells change?
- `pg_page_quick_edit_columns($user)` (`list_table_edit.php`), built once per list in `pg_page_list_source()` (`$view['quick_edit']`): name, folder, page type, the style of the device type shown (staff), form name and form enabled (custom form pages, users who may set that type), searchable, comments, site map. Without a user only the three switches.

## Where do the choices come from?
- Folder and style choices are the page screen's pickers: `pg_page_quick_folder_choices()` (no Workspace, no Recycle Bin, only folders the user may edit) and `select_style()`/`get_mobile_style_options()` read by `pg_page_quick_option_pairs()`. A visual editor page's style cell stays text and the write refuses it.

## How are the folder choices coloured?
- Each folder option wears its folder's own access class (`pg_page_quick_folder_classes()`, as `select_folder()` prints it; none when the folder inherits), passed as the column's `option_classes` and sent by `pg_list_edit_table()` as a third item of the option's pair. `tintSelect()` in `pg_list_table_edit.js` gives the closed select the chosen option's class on open, change, Escape and a saved answer.
- The cell's own access class (`td.private`) used to colour the select and every option through `.private *`; `select.pg-list-table-field` and its options now reset to `--pg-fg-body` and take only their own class (`backend.src.css`).

## How is each value written?
- Folder, style and switches are written by Modify Selected's rules (`pg_page_quick_decide()` → `pg_page_bulk_decide()`/`apply()`): tag cloud, SEO stale; the destination folder needs edit rights and passes `pg_page_form_folder_errors()`. While another theme is previewed the style goes to `preview_styles`, as in Modify Selected.
- The name: `pg_page_form_clean_name()`, `check_name_availability()`, SEO stale, and for staff the old address redirects (`pg_short_link_page_renamed()`, the page screen's box on by default). What became of the old address comes back as `notice`, which `pg_list_table_edit.js` shows as a toast.
- Form name is unique among `custom_form_pages`; both form fields stamp the page.
- The edit URL carries the view (`view_pages.php?filter=…`) so the answer re-reads the row in the same view (`pg_list_table_record_row()`); a page that left the view gets its cell from the stored value.
- Page type (`pg_page_quick_type_write()`): options are `select_page_type()`'s; the cell is a field only when the user may set the present type. A custom form with submissions keeps its type; the old type's row, form list links and search keywords go; the new type starts as a new page (`pg_page_quick_type_properties()`); site map off for a type it does not list. The notice sends a new form to the form designer.

Related: docs/memory/ui/list-table-quick-edit-values.md, docs/memory/ui/list-table-quick-edit.md
