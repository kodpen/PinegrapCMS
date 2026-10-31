---
name: name-length-refusal
description: File and Catalog Manager, folder screens and Pages Find & Replace refuse a name longer than its varchar column instead of letting the non-strict session cut it
module: files
---

## Which names are checked, and against what?
- `pg_name_too_long($name, $limit = null)` and `pg_name_max_length()` (100) in `includes/fn/files.php`; `mb_strlen()` in UTF-8, so the limit is in characters like a utf8mb4 varchar's.
- 100: `folder.folder_name`, `page.page_name`, `products.name`, `product_groups.name` and `product_groups.short_description`. 255: `page.page_title`, `page.page_meta_description`. No migration widens them; widening is a schema step.

## Where is a long name refused?
- `includes/explorer/handle_items.php`: folder create (before `get_unique_name()`, so its `[1]` is never cut off) and the folder/page rename (before `check_name_availability()`); file renames keep `prepare_file_name()`'s own rules. Answer: `status=error` with "The name can be at most {var:1} characters long.", which the File Manager shows as a toast.
- `includes/explorer/handle_catalog.php`: group create and group/product rename, same answer. Bulk Rename calls these endpoints row by row, so a prefix/suffix that pushes a name past 100 is refused per row.
- `add_folder.php` (liveform error on Name) and `edit_folder.php` (error page, before the parent move); both name boxes carry `maxlength="100"` (`includes/forms/folder_form.php`).
- `mass_edit.php`: Web Browser Title has `maxlength="255"`; Find & Replace leaves a value that would pass its column unchanged, checks the page name's availability on the value it would store, and adds the notice "N value(s) skipped: longer than the field allows.".

## Why?
- The session is not strict (`data/sql-silent-wrong-results.md`), so a longer value lost its tail without an error. The rename handlers sent back the uncut name, so the list showed a name never stored; a cut page name could match another page's address (the `page_name` index is not unique); a 98-100 character folder name lost the `[1]` of `get_unique_name()`.
- Same rule as the external API limits (`api/api-text-field-limits.md`). Test: `tests/name_length_refusal_test.php`.
