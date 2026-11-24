---
name: layout-type-converter-editor
description: LE6b: a file page's System region in the editor, the dropped pieces listed in the Region panel, Convert to design elements from the layout file (layout_code convert_type)
module: layout_convert
---

## What does the Region panel show on a file page?
- `_design.classicWidgets` (`pg_classic_widget_offers()`) gives a page with its own layout file of a mapped type `file` true and `dropped`: rows from `pg_layout_dropped_rows()`, "line 42 · a condition on $watcher_option · dropped", a piece cut at 60 characters. A hosted site draws no file: no `file`.
- Pieces dropped: the line "Its layout has pieces that have no design element", five rows then "Show all N"; then "Convert to design elements" filled; "Edit layout (PHP)" quiet below (`_sdLayoutPropsHtml()`).
- Convert (`_sdClassicFileConvert()`): the confirm names how many pieces go and that undo brings the region back; `layout_code` / `convert_type` (role <= 1, token, page cache clean, writes nothing) takes the kind's starter, answers `kind`, `tree`, `cfg`, `dropped` rows by `pg_layout_type_file_convert()` (the run's builder too); the widget goes in the region's place, one undo step; the toasts name the widget and the dropped rows.
- A file of a type with no map yet (LE7 to LE10) or not readable keeps the starter convert, which loses the theme's markup.

## Tests?
- `tests/layout_convert_test.php`: `pg_layout_type_file_convert()`, `pg_layout_dropped_rows()`; `tests/designer_layout_code_test.php`: `convert_type` is clean. The editor path has no unit test (it needs the editor and a database).

Related: docs/memory/designer/layout-type-converter.md, docs/memory/designer/layout-form-converter-editor.md
