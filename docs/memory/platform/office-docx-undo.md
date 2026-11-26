---
name: office-docx-undo
description: Undo and Redo of a Word document typed on its sheet (OP3, PR #11963): the bar's pair, Ctrl+Z / Ctrl+Y / Ctrl+Shift+Z, what one change is, what a press puts back
---

## Where are Undo and Redo?
- At the start of the document's format bar, before Bold: two `pg-icon-button pg-icon-button-sm` with `bi-arrow-counterclockwise` "Undo (Ctrl+Z)" and `bi-arrow-clockwise` "Redo (Ctrl+Y)" (strings `doc_undo`, `doc_redo` in `pg_office_strings()`). Greyed while there is nothing to undo or redo. A press keeps the caret on the sheet (mousedown prevented).
- Keys on the sheet or a picked paragraph: Ctrl/Cmd+Z undo, Ctrl/Cmd+Y and Ctrl/Cmd+Shift+Z redo (`historyKey()`). The browser's own undo is never used: its menu's `historyUndo` / `historyRedo` input goes to the same list.

## What is one change?
- Each Enter (split), Backspace or Delete join, typing over or deleting a selection of several paragraphs, paste, Bold/Italic/Underline (key or bar), a Paragraph style, Delete or Keep the paragraph, and Esc (so Ctrl+Z after Esc brings the typing back) is one (`oneChange()`): the state before it is kept only when the change altered what a save would send (`meaning()`), so a refused key leaves no empty step.
- Typing the browser does inside one paragraph is one change while the caret goes on from where the last letter went, the same way (typing or deleting): a click elsewhere, the other kind, or a selection starts a new one (`typedRun()`, `run`).
- The list keeps the last 100 states (`changeList(100)`); a new change drops what could be redone. `view.reset()` (dropping every change) empties it; the list lives with the view, so the file opened again starts with none.

## What does a press put back?
- `capture()` keeps the typed texts (with the one being typed), the looks, the kinds asked for, the deletes, each added paragraph's node with the element before it and its maps, the paragraphs hidden by a join, and the caret or the picked paragraph.
- `restore()` takes added paragraphs out, puts the state's back in reading order after their element, rebuilds `nodes` from the sheet, redraws each paragraph touched and puts the caret back. Added paragraphs keep their names (`n1`…); the counter only grows.
- Redo gets the state the Undo left, with its caret.
- The paragraph being typed in shows a kind asked for only once left, as before.

## What was checked?
- `tests/office_document_page_test.php`: `changeList()` and `historyKey()` in Node; the source (the pair, the keys, Enter as one change, reset empties the list, no variable hiding the new functions).
- Sandbox, Chromium (Playwright), 1440 and 390 px, the File Manager's file window and a channel's preview.
- A new document (type, Enter, type; Ctrl+Z ×3, Ctrl+Y, Ctrl+Shift+Z, the buttons; bold and its undo; Backspace join and its undo; typing after an undo drops Redo; all undone and redone).
- One with a heading, a bulleted list and a table: Enter in a list item, typing in a cell, Backspace over two items, Delete the paragraph, Paragraph style, Esc then Ctrl+Z. Saves read back with python-docx as shown. Not tried: Firefox, Safari, a phone keyboard, an input method.

Related: docs/memory/platform/office-docx-page-keys.md, docs/memory/platform/office-docx-page.md, docs/memory/plans/office-document-page.md
