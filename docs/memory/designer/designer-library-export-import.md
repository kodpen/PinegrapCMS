---
name: designer-library-export-import
description: My library's Export and Import (ML4): the export file and its name, names kept beside, the import's report, the endpoints and the part
module: designer_library_io
---

## What does an export file hold?
- `pg_library_export_text($items, $at)` (`includes/fn/designer_library_io.php`, pure): `format` `pinegrap-library`, `version` 1, `exported` (Y-m-d), `items` (each as its own file holds it, without `id`, `file`, `order`, `dropped`).
- The file is named after the site with "-library" (`pg_library_export_file_name(ORGANIZATION_NAME)`: "Studio Calm" gives `studio-calm-library.json`). One chosen item gives a file with one item; the format is the same.
- `pg_library_export_items($ids)`: the chosen row ids in the library's order, every item when none is chosen.

## Where is Export started?
- From the foot of "Manage library" (ML3) with its choice: `_sdPartCall('libraryfile', 'exportItems', [ids])`. No command palette row and no bar button (the drawing's three rows are "Save to my library", "Manage library", "Import to my library"). One line says "3 items exported to studio-calm-library.json."

## What does an import accept, and what does it refuse?
- An export file or one item's own file, at most 1 MB; one item that fails the checks refuses the whole file. The checks and the refusal's words: `designer-library-import-checks.md`.

## Under which names are imported items kept?
- Names are never changed: a name the library has is kept beside it (both are kept). `pg_library_import_plan()` gives each item its note for the report: `name_kept` ("The library has an item of this name: both are kept."), `group` ("Added to “Heroes”."), `new_group` ("Added to the new group “People”."), `no_group` ("Added with no group."). Case does not make another name or group. Imported items follow the library's last `order`.
- `pg_library_import_save()` writes them with `pg_library_save()`; a write that fails stops it, the items written before stay.

## Where does it run?
- `designer/library_export` (takes `ids`, answers `count`, `name`, `text`) and `designer/library_import` (takes `text`, read in the browser since `api.php` reads JSON, and `file`, the name for the messages; answers `rows`). Both ask `pg_library_may('export'|'import')` and are outside the content roles' allow-list; the module is loaded by these two cases only.
- Part `libraryfile` (`assets/js/style_designer_libraryfile.js`), handed only to designer access full. The command palette row "Import to my library" shows only when the part is handed over. The window shows "Checking the file…" with Cancel, then the report with "{n} items added. They are in the Blocks list now." and Done. A file over 1 MB is refused before it is sent.
- A kept import fires `sd-library-changed` on `document`, for the "My library" group (ML2) to read the library again. Cancel after the file was sent closes the window; the kept items are then told in one line.

## How is it tested?
- `tests/designer_library_io_test.php`: the round trip and the file's name, the report's notes and order.
