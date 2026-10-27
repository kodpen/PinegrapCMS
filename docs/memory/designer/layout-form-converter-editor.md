---
name: layout-form-converter-editor
description: W22b "Convert to design elements" on a custom form page's system region: the button, the layout_code convert action, the widget placed after the region, toasts, rows the sync keeps
---

## How does the editor use it?
- The `system` node's Region panel shows "Convert to design elements" on a custom form page (`_design.layoutFormPages`, `pg_layout_form_page_ids()`). `_sdLayoutConvert()` asks `layout_code` / `convert` (role <= 1, token; reads the layout, the rows, the submit label and the generated text; writes nothing; page cache clean), creates a custom_form widget (`form_source: 'page'`) and places it AFTER the region, one undo step; the region stays until the designer deletes it. Toasts name the rows added and what was left out (`SD_LAYOUT_DROPPED`).
- The page save then writes the form from the widget; information and signature rows are never deleted by that sync (`pg_cf_sync_page_form()`), so the region beside keeps drawing them.

## Tests?
- `tests/designer_layout_code_test.php`: `convert` is a clean (page cache) sub-action. The editor path has no unit test (it needs the editor and a database).
