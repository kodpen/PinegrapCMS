---
name: office-document-page
description: Plan (owner 2026-11-26): the document editor typed on a page, not in a box: paragraphs written where they stand, Enter and Backspace as in a word processor, Undo and Redo; steps OP1 to OP4
priority: 6
---

## What is asked?
- Owner (2026-11-26, in the architect's window): "yeni tablo ve word belgesi ekleme ekranları çok kararsız. word ekranı bir word yazma alanı gibi değil input gibi. […] her ikisi için yeteneklerini genişletelim, stabiliteyi artıralım."
- The workbook's half is `plans/office-sheet-steady.md`. The abilities on top (his answer to poll 235, agent channel message 14401: all eight) start when OP3 has landed: `plans/office-document-abilities.md`.

## What is wrong today? (read in memory and the code, 2026-11-26; not tried in a browser)
- A paragraph opens in a box at the click and closes with Enter (`platform/office-paragraph-edits.md`): it reads as a form field. A new paragraph is a button of the bar (`platform/office-docx-add-delete.md`), not the Enter key.
- A new document is one empty paragraph (`platform/office-new-files.md`), so the first thing a person meets is that box.

## Which steps? (M about 1,000 product lines, S under 400; each waits for the one before it)
- OP1 (M, `renderDocument()` in `pg_office_view.js`, the editor's block of `backend.src.css`): the page.
  The document is drawn as a white sheet, 816 px wide at most, with 96 px side margins (16 px under 600 px), on the window's quiet ground; white in both themes, as file content.
  A paragraph that may change is typed where it stands, the caret where the click fell; no box. A locked one is not typed into and says why as today. Paste is plain text. The save entries stay as they are.
- OP2 (M): the keys of a page. Enter at a paragraph's end adds one after it; inside, it splits it: the text is cut and the rest added after it with its marks.
  Backspace at a paragraph's start joins it to the one before: its text and marks go there and it is deleted; refused where a delete is refused, with that reason. The arrows cross paragraphs.
  Bold, Italic and Underline act on a selection across paragraphs. The bar's "Add a paragraph after this one" leaves; "Delete the paragraph" stays.
- OP3 (S): Undo and Redo on the document's own list of changes: the `pg-icon-button` pair of the workbook's bar (`bi-arrow-counterclockwise`, `bi-arrow-clockwise`), Ctrl+Z, Ctrl+Y and Ctrl+Shift+Z.
- OP4 (S) Close-out: `platform/office-paragraph-edits.md`, `office-docx-format.md`, `office-docx-add-delete.md` and `office-overview.md` corrected, this file deleted.

## What does every step keep?
- The server's save entries and their order (text, format, added, deleted, kind) and every refusal: a split or a join is written with them. A step that finds it needs a new entry adds it in `office_docx_paragraphs.php` with its test.
- Save stays a button and nothing is saved by itself; in a channel replace or copy as today (`workspace/workspace-office-edits.md`).
- No library, no schema, the never-built list of `platform/office-files-scope.md` (no page layout: the sheet is one long page); every text through `lang()`; controls copied from the bar (look A).
- Each step tries the page in a browser on a sandbox, in the file window and a channel's preview, at 1440 and 390 px, on a new document and on one with a table and a list: no unit test reaches the typing.
- The workbook plan changes the grid's functions of the same script; the two plans may run side by side, the coordinator's call.

Related: docs/memory/plans/office-sheet-steady.md, docs/memory/platform/office-overview.md, docs/memory/platform/office-paragraph-edits.md
