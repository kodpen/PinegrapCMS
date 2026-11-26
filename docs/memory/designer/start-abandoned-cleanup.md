---
name: start-abandoned-cleanup
description: What an abandoned Figma or Canva start deletes (#11705): the stop step, the window's close, a failed finish, the Cleanup tool's work files, the Canva drop by session ids
module: figma_start, canva_start
---

## What is the rule?
- Architect (2026-11-26, agent channel request 2991): an abandoned start does what "Stop" does: it deletes what it stored itself and nothing uses. No new text in the window.
- Not covered and no fault: a browser closed without the window's close leaves Canva pages; they keep their description ("A Canva page, the model of a sketch page") so a person finds them. A design opened and never published is the template rule's matter (`pg_design_template_discard()`).

## What does the Figma stop delete?
- `pg_figma_import_stop()` removes the work file first (a frame step still fetching then deletes what it stores, `pg_figma_import_keep_step()`), then `pg_figma_import_discard()` deletes what `pg_figma_stop_targets()` names: the fetched files by stored id (`pg_delete_file_record()`), the `import:figma/<key>` shared rows, and `Figma/<file name>` when the import made it (`made_folder`, from `pg_di_folder_path()`'s `created`) and no folder, file or page is in it.
- A finish that ends after the work file was removed deletes the shared rows it made and writes nothing back.

## When does the Figma window stop an import?
- `abandon()` in `assets/js/designer_figma.js`: Stop, the window's close (`hidden`) before "Open the design", Back from the variables card, a finish that fails or answers an error. A start answer arriving after the close is stopped too. "Open the design" sets `leaving`, so its navigation stops nothing.
- The Cleanup tool (`clean_up.php`), before it removes a `figma_<sha1>.json` work file under `data/temp` (`pg_figma_is_work_file()`), runs the same stop from that file (`pg_figma_import_stop_path()`).

## What does the Canva drop delete?
- `pg_canva_store_page()` keeps each stored file id in `$_SESSION['pg_canva_pages']` and returns it as `page.id`.
- `drop()` in `assets/js/designer_canva.js` sends ids to designer › `canva_drop`: the bin of a page, the cross on an upload that reached the server (after `xhr.upload.onload` the answer is awaited, not aborted, then dropped), a page over the limit, Cancel and the window's close (`leave()`, which also empties the queue).
- `pg_canva_drop_pages()` deletes only ids `pg_canva_drop_ids()` accepts (asked and stored by this session), never a file found by name or description, and never one a saved page, region, design or shared component names (`pg_canva_file_in_use()`, a LIKE on the name; a failed query keeps the file).
- "Create design" and the questions' finish set `leaving` (`keep()`), so the navigation drops nothing.

## Tests?
- `tests/figma_start_test.php` (`pg_figma_stop_targets()`, the work-file name, source pins) and `tests/canva_start_test.php` (`pg_canva_drop_ids()`, source pins). The window's close, the drop and the Cleanup run were not run in a browser or on a database.
