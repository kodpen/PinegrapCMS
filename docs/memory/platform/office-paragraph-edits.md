---
name: office-paragraph-edits
description: Word paragraphs changed in the file window and the Workspace (includes/fn/office_edit.php): which paragraphs, how new text goes into the runs, refused documents
---

## How is a paragraph of a document changed?
- The same read with `edit` as a workbook (docs/memory/platform/office-cell-edits.md): `pg_office_docx_read()` marks each drawn paragraph with its position among all `w:p` of word/document.xml in document order (`data-p`, `pg_office_docx_paragraphs()`, the same count the save uses) and, when it cannot change, why (`data-lock`); `edit.paragraphs` holds the plain text of those that can, by position, and `version` the sha1 of the bytes.
- `pg_office_view.js`: a click picks a paragraph, a second click (a double click too, with its word selected), Enter or F2 opens it in a box at the click; Enter keeps and goes to the next, Shift+Enter is a line break, Tab/Shift+Tab move, Esc undoes, Ctrl+S saves. The text typed so far already counts, so Save takes it before Enter (the grid does the same now). A changed paragraph shows plain until saved; links follow only with Ctrl/Cmd.
- Saves: `explorer_file_save` with `paragraphs` ([{p, text}]) and `office_version`; in a channel `ws_file_office_save` with `paragraphs`, into a copy (docs/memory/workspace/workspace-office-edits.md). `pg_office_docx_save()`: same lock, version check, copy-build-verify-rename as cells (`pg_office_save_package()`); only word/document.xml is rewritten (DOM, no blank dropping).

## How does new text go into the runs?
- Text is what a person types: `\t` a tab, `\n` a line break (`w:br`, `w:cr`), U+2011 / U+00AD the non-breaking and optional hyphens. `pg_office_text_hunks()` keeps the common start and end, then compares words (LCS over word/number/space/char tokens; a middle over 250000 cells, or past the save's 2000000 budget, is one change). Each change goes into the runs it covers; untouched runs keep their XML. Added text joins the run of the character before it, as typing continues formatting, except right after a link (then the run after); a replacement takes the formatting of its first character, as Word does. Emptied runs go; a paragraph without runs gets one formatted like its mark. Changed from the plan sketch: runs are not merged into the first run's style.

## What is never changed?
- Paragraphs with tracked changes (also formatting changes waiting for review), fields (page numbers, dates, TOC, `fldSimple`), comments, foot/endnote marks, pictures/shapes/equations/symbols, page or column breaks, content controls, smart tags, anything unknown; and by place: inside a content control or custom XML, a text box, a cell merged into another (`vMerge`/`hMerge` continue). Whole documents: signed, protected (`documentProtection` enforced with edit not none, `writeProtection`), Track Changes on, main part over 8 MB, not UTF-8 or with a DOCTYPE (`edit.why`). 2000 paragraphs a save, 100000 characters a paragraph.

## What was checked, and what not?
- `tests/office_edit_test.php`; 24 Word-written documents (Apache POI test data) and python-docx/LibreOffice ones: untouched paragraphs byte-identical (C14N), new texts read back by python-docx and LibreOffice 24.2, namespace declarations kept; sandbox with Playwright (file window, conflict, protected document, Quick Look, Workspace replace and copy, dark theme). Not checked: Word itself, Pages, Google Docs.
