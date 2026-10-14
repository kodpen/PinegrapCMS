---
name: slug-generation
description: Which function builds address/slug names for forms, products, files and pages, the single transliteration point, the lowercase-order rule, and the files.name invariant.
---

## Where is Turkish/accented text converted to ASCII?
- Only `pg_transliterate_to_ascii()` (functions.php). Never write a new accent table or "extend" an existing one; route through it.
- Do not restore the old accent table (until 2026.4.4): it had collapsed to U+FFFD in a broken encoding conversion, 66 keys identical, dead.

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
