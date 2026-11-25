---
name: designer-library-export-import
description: My library's Export and Import (ML4): the file's format, the import's checks (script, event attribute, address kinds), names kept free, the endpoints, the library part and its command palette rows
module: designer_library
---

## What does an export file hold?
- `pg_library_export_text($items, $at)` (`includes/fn/designer_library.php`, pure): `format` `pinegrap-library`, `version` 1, `exported` (Y-m-d), `items` (each as its own file holds it, without `id`, `file`, `order`, `dropped`). Offered as `my-library-<date>.json` (`pg_library_export_file_name()`).
- Export writes every item of the library; choosing items is the "Manage library" window's (ML3).

## What does an import accept?
- `pg_library_import_read($text)`: an export file or one item's own file (`pinegrap-library-item`). At most 16 MB (`PG_LIBRARY_EXPORT_MAX`) and 200 items (`PG_LIBRARY_EXPORT_ITEMS`).
- Every item passes `pg_library_item_decode()` and `pg_library_item_problem()`. One item that fails refuses the whole file: `array('error' => code, 'item' => name or #place)`; `pg_library_import_message()` makes the sentence the toast shows.

## What do the checks refuse?
- `pg_library_item_problem()` walks the tree and the trees of its widgets and shared components, the widget settings, the rules and the variables. Codes: `script` (a `script` node or `tag`, a `<script>` in a string, `srcdoc`, CSS `expression(`, `-moz-binding`, `behavior:`, `@import`), `event` (an `_attrs` name or a tag attribute starting with `on`), `address`, `deep` (past 80 levels).
- `pg_library_address_ok()`: allowed are no scheme (a site path, `#`, `?`, `//host`, a `{{token}}`), `http`, `https`, `mailto`, `tel`, `sms`, and `data:image/` png, jpeg, gif, webp, avif (not svg). Entities and blanks are taken out before the scheme is read.
- An address field (`pg_library_address_key()`: `href`, `src`, `srcset`, `action`, `poster`, `url`, `link`…) is checked whatever its text; any other string only when it starts with `javascript:`, `vbscript:` or `livescript:`, holds a tag, or holds `url(`. A sentence like "Learn javascript: basics" passes.

## Under which names are imported items kept?
- `pg_library_import_names()`: a name the library has in the same group (case folded), or one taken earlier in the file, gets " (2)", " (3)"…, cut to fit 60 characters; nothing is replaced. The imported items follow the library's last `order`.
- `pg_library_import_save()` writes them with `pg_library_save()`; a write that fails stops it, the items written before stay.

## Where does it run?
- `api.php` `designer/library_export` (answers `count`, `name`, `text`) and `designer/library_import` (takes `text`, the file read in the browser, since `api.php` reads JSON); both ask `pg_library_may('export'|'import')` and are closed to content roles by the panel's allow-list.
- Part `library` (`assets/js/style_designer_library.js`, `pg_designer_parts()`), handed only to designer access full. The command palette rows "Export my library" and "Import into my library" (`_getCpActionItems()`) show only when the part is handed over; no bar button. Export downloads a Blob; Import opens the file chooser, refuses a file over 16 MB before sending it.

## How is it tested?
- `tests/designer_library_test.php`: the address kinds, the text checks, the item checks in each place, the round trip, the refusals, the names.
