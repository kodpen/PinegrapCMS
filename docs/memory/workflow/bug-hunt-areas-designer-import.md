---
name: bug-hunt-areas-designer-import
description: The bug hunt of the designer's HTML, ZIP and paste importers on the server (hunt 55, 2026-11-24, #11501): the seven faults fixed, what was read clean, what was not run
---

## Which area was hunted?
- Hunt 55 "designer-import", named by the architect (channel message 13112): `includes/designer_import.php`, `designer_import.php`, `import_design_zip.php`, `api.php designer/import_paste` and `designer/import_fragment`, server side only. Asked for: the archive's paths and sizes, what a fetched address may reach, file types written to the file manager, the rows an import makes and their discard, scripts and event attributes in imported markup.

## What did the hunt's PR fix?
- A `<script src>` of a type the browser does not run (`type="text/plain"`, a consent manager's held-back tracker) became a design script and ran on every visit; it is left out with a warning (`pg_di_script_type_runs()`).
- A `<script>` inside a paragraph, heading or link stayed in its text: a page import ran it twice (it is also the page's script), a paste ran it after saying it was left out. `pg_di_inner_html()` leaves scripts out.
- ZIP entries the upload rule refuses (`.phtml`, `.cgi`, `.asp`, `web.config`) were written under a defused name; now left out with the upload screens' warning (`pg_di_entry_type()`).
- An entry more than 16 folders deep (`PG_DI_MAX_DEPTH`) is left out: each folder was a row. A folder name past 100 characters is cut before its lookup (`pg_di_folder_segment()`), so a re-import finds it.
- The second of two same-named entries took `name[1].ext` unchecked and overwrote a library file of that name; each candidate asks the library (`pg_di_free_file_name()`).
- A single uploaded `.html` was parsed at any size; it has the 8 MB page ceiling (`PG_DI_MAX_HTML`).
- An imported page name past 100 characters (long slug, folders in front) was refused at its save; candidates are cut to fit (`pg_di_page_name_candidates()`).

## What was read clean?
- No designer import path fetches an address on the server: absolute stylesheet and script addresses are kept as links the browser loads. The site importer's fetch is `import_design.php` (`core/php-regions-eval-and-content.md`).
- Role gates: the upload refuses roles above Designer in JSON; the paste opens only `import_fragment` to content roles, and its nodes pass the save gate (`designer/designer-content-roles-save-gate.md`).
- Archive ceilings (`pg_zip_import_open()`, `pg_zip_entry_contents()`), the undo of a refused import, the `import:` shared rows' discard.

## What was left as it is?
- Event attributes (`onclick`) and `javascript:` links in a paste stay: a content operator's text is raw HTML already, so the paste adds no right.

## What was not run?
- No database here: the ZIP loop and the folder and file writes were not run. Pinned by `tests/designer_import_hunt_test.php` (pure helpers, page conversion, source checks).

Related: docs/memory/workflow/bug-hunt.md, docs/memory/designer/html-import-and-paste.md, docs/memory/designer/designer-import-validation-theme.md
