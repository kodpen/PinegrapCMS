---
name: list-table-quick-edit-short-links
description: Short links list quick edit (includes/fn/short_link_list_edit.php): fields per destination type, the split destination columns, the edit screen's own checks and save
module: short_link_list_edit
---

## Which cells change?
- Every value `edit_short_link.php` changes (`pg_short_link_quick_edit_columns()`): Name, Destination Type, Page, Destination, Tracking Code and How it answers (only once `redirect_code` exists, `pg_short_link_redirects_ready()`).
- To make each value one field, the one Destination cell ("page/product?t=code") became Page, Destination and Tracking Code, and the answer badge left the name for its own column.
- The field a cell carries follows the link's destination type (`pg_short_link_quick_field()`): Page is `page_id` (any page), `group_page_id` (catalog and catalog detail pages) or `product_page_id` (catalog detail pages); Destination is `product_group_id`, `product_id`, `url` (text) or `file_id`; a URL or file link has no Page and no Tracking Code, a page link no Destination.
- A one-time link (no name) has no field: it is changed in the File Manager. A cell whose stored page, group or product is not offered (in the Recycle Bin, outside the user's folders) stays text (`pg_short_link_quick_attributes()`).
- The file cell searches (`search`, kind `file`, #8424): it is a field for any file the link has, named in `data-pg-edit-text` (a binned file under its old name, marked `[IN RECYCLE BIN]`); a file row that is gone leaves it text. Before, it listed every file (`get_file_options(true)`) on each load.
- Text: Created, Last Modified, Visit. `tests/short_link_list_edit_test.php` fails for a new column that is neither.

## How is a change checked and written?
- `view_short_links.php` answers `pg_list_edit_request()` (managers and up) before drawing the screen.
- `pg_short_link_quick_update()` fills a throwaway liveform (`short_link_quick_edit`) with the stored link as the screen opens it (`pg_short_link_form_values()`) plus the one change (`pg_short_link_quick_form_values()`), runs the screen's `pg_short_link_form_validate_name()`, `pg_short_link_form_validate_destination()` and `pg_short_link_form_answer()`, and writes with `pg_short_link_form_save()`, the screen's own save. The first field error is the refusal's message. The rewrite file must be writable, as on the screen.
- A destination type change keeps the stored page in the new type's picker; a type whose target is missing (page to URL) is refused with the screen's "… is required".

## What does the answer redraw?
- The row read again, every cell with its new value. A cell keeps the field it was drawn with, so after a destination type change the Page and Destination cells name the old type's field until the list reloads; a write through one is refused with "The short link has changed since the list was loaded".

Related: docs/memory/ui/list-table-quick-edit-values.md, docs/memory/ui/list-table-quick-edit-contacts.md
