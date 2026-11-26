---
name: office-sheet-steady
description: Plan (owner 2026-11-26): the spreadsheet editor made steady: every change of a workbook taken at once and saved together, no "save first" refusal, Undo and Redo; steps OF1 to OF5
priority: 6
---

## What is asked?
- Owner (2026-11-26, in the architect's window): "yeni tablo ve word belgesi ekleme ekranları çok kararsız. […] excel tablosu arkaplan, eğik yazı veya bold renk değiştirme vs. alanları çalışmıyor, ayrıca bir belgede tablo satırlarını düzenleyemedim kapatıp yeni açınca düzeldi. her ikisi için yeteneklerini genişletelim, stabiliteyi artıralım."
- The document's half is `plans/office-document-page.md`. The abilities on top (his answer to poll 235, agent channel message 14401: all eight) start when OF4 has landed: `plans/office-sheet-abilities.md`, then `plans/office-sheet-formulas.md`.

## What is wrong today? (read in the code, 2026-11-26; not tried in a browser)
- A workbook takes one kind of change a save (`platform/office-overview.md`). `setFormat()` in `pg_office_view.js` does nothing while a typed cell, a row, a column or a sheet change waits: only the status line says "Save the other changes first, then change the format." A second range is refused the same way.
- While a format, a row, a column or a sheet change waits no cell can be typed into (`sheetEditable()`), until the save or a reopening. `pg_office_xlsx_save()` refuses a list that mixes kinds.
- The server is sound: on a new spreadsheet bold, italic, text colour, fill, alignment and number each save and read back.

## Which steps? (M about 1,000 product lines, S under 400; each waits for the one before it)
- OF1 (M, `includes/fn/office_edit.php`): one save takes an ordered list of changes of every kind: cells, format ranges, a row or column inserted or deleted, a sheet added, renamed, deleted or moved.
  Each is applied to the workbook as the one before left it, with today's writers and refusals, on one copy under one lock and one version check. One refusal writes nothing and names the change's place in the list.
  Both doors (`explorer_file_save`, `ws_file_office_save`). Tests: a mixed list read back; each refusal leaves the file byte for byte.
- OF2 (M, `pg_office_view.js`): cells and formats in one list. A format is taken while cells wait, cells are typed while formats wait, on any number of ranges; each is drawn at once. The strings `format_first` and `format_one` leave. The status line counts: "3 changes not saved".
- OF3 (M): rows, columns and sheets in the same list. The grid and the tabs are redrawn from the list, so a row put in is typed into before the save; a position sent is the workbook's at that point of the list.
- OF4 (S): Undo and Redo for the list: a `pg-icon-button` pair (`bi-arrow-counterclockwise`, `bi-arrow-clockwise`) at the bar's start in place of the quiet Undo; Ctrl+Z, Ctrl+Y and Ctrl+Shift+Z; one press, one change. Escape no longer drops a waiting change.
- OF5 (S) Close-out: `platform/office-overview.md` and the `platform/office-sheet-*` and `office-cell-edits.md` files corrected ("one change a save" leaves), this file deleted.

## What does every step keep?
- Save stays a button and nothing is saved by itself; in a channel replace or copy as today (`workspace/workspace-office-edits.md`).
- No library, no schema, the never-built list of `platform/office-files-scope.md`; every text through `lang()`; controls copied from the bar (look A).
- OF2 to OF4 try the grid in a browser on a sandbox, in the file window and a channel's preview, at 1440 and 390 px: no unit test reaches the typing. The PR says what was tried.
- The document plan changes `renderDocument()` of the same script; the two plans may run side by side, the coordinator's call.

Related: docs/memory/plans/office-document-page.md, docs/memory/platform/office-overview.md, docs/memory/platform/office-sheet-format-controls.md
