---
name: slug-generation
description: Which function builds address/slug names for forms, products, files and pages, the two transliteration tables, the lowercase-order rule, and the files.name invariant.
---

## Where is Turkish/accented text converted to ASCII?
- A name the software builds for a URL (an address name, a file name) is brought down to ASCII: IIS hands PHP the request path re-encoded in the server's ANSI code page, so a stored UTF-8 name is never matched and the visitor gets a 404. Page and short-link names stay as typed (table below).
- Two tables, no third: `pg_transliterate_to_ascii()` (`includes/fn/content.php`, table in `pg_transliterate_ascii_table()`) for address names, and the map inside `pg_ascii_file_name()` (`includes/fn/files.php`, called by `prepare_file_name()`) for file names, which turns anything left outside `A-Za-z0-9._-[]` into `_`. A new name goes through one of them (a file: `prepare_file_name()`); never write a new accent table.
- Do not restore the old accent table of `create_address_name()` (until 2026.4.4): it had collapsed to U+FFFD in a broken encoding conversion, 66 keys identical, dead.

## Which function for which name?
| What | Function | Format |
|---|---|---|
| Submitted form (blog/forum) | `create_address_name()` | lowercase + hyphen: `bulut-bilisimin-avantajlari` |
| Product / product group | `prepare_catalog_item_address_name()` | case kept, underscore: `Cay_Bardagi` |
| File name | `pg_ascii_file_name()` | name kept, underscore; `[ ]` are KEPT (version suffix) |
| Page name, short-link name | NO transliteration | the name the operator typed; `seo.php` reports it as `url_non_ascii` |

## Transliterate first or lowercase first?
- Transliterate first, THEN `mb_strtolower()`. The reverse turns `İ` into `i` + combining dot (U+0307); the dot becomes a separator and splits the word (`İçin` -> `i-cin`).

## What is `files.name` and what must renaming code do?
- `files.name` IS the address. Every reader resolves the file as `FILE_DIRECTORY_PATH . '/' . name`, and the router matches the requested path against this column.
- Any code that changes the name must also rename the file on disk (`edit_file.php`, save branch).
- If `rename()` fails, the `UPDATE` must not run at all; otherwise the row points to a nonexistent file (before 2026.4.4: zip/image downloads broke, text types left the old file orphaned on disk).

## How does the product screen preview the address?
- `previewAddress()` (`assets/js/product_builder.js`) runs the steps of `prepare_catalog_item_address_name()` with the table PHP passes as `window.PinegrapProductBuilder.asciiTable`; on Edit, a blank or unchanged Catalog Name box shows `storedAddress`, because the save keeps it. `tests/product_address_preview_test.php` pins the character class on both sides.
