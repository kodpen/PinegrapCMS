---
name: office-docx-page-keys
description: Keys of a Word document typed on its sheet (OP2, PR #11940): Enter splits, Backspace and Delete join, selections and arrows cross paragraphs, B/I/U across them
---

## Which keys does the page take?
- Enter (`splitParagraph()`): the selection is taken out, the text after the caret goes with its look into a new paragraph after it (`cutLook()`), the caret at its start. At the end it adds an empty one; after a heading's end that one asks Normal text. Refused after a paragraph in `edit.shut` (`doc_shut`). A phone keyboard's `insertParagraph` does the same.
- Backspace at a paragraph's start, Delete at its end (`joinParagraphs()`): the later one's text and look go to the end of the earlier one, the later one is deleted (hidden till the save; an added one goes). Refused with the delete's `keep_*` reason, a locked or deleted neighbour's own reason, or "Paragraphs in different table cells are not joined." When the later one is a cell's or the document's last (`keep_cell`, `keep_last`), the earlier one goes into it instead, if it may go.
- Typing, Enter, Backspace, cut or paste over a selection of several paragraphs (`removeAcross()`) takes them out first, by the same rules; a locked paragraph between refuses it. A whole paragraph selected (three clicks) is typed over inside it.
- Text moved into another paragraph or a new one is sent with every format on or off (`exactBase()`), as the server puts it in a run whose look the view does not know.
- Arrows: Left at a start and Right at an end go to the paragraph beside; Up and Down are the browser's. Shift+Enter: a line break. Tab and Shift+Tab: next and previous paragraph, at either end the focus leaves. Esc: what was typed since the caret came in is undone; with nothing typed Esc goes on to the window.
- Ctrl/Cmd+B, I, U and the bar act on the selection, also across paragraphs (`selectedParts()`), kept selected after the redraw; Ctrl/Cmd+S saves. `format*` input and a drop are refused in `beforeinput`. Ctrl/Cmd+Z, Ctrl/Cmd+Y and Ctrl/Cmd+Shift+Z undo and redo on the document's own list (`office-docx-undo.md`).
- Paste takes `text/plain` only. A link is followed with Ctrl/Cmd and a click; a plain click places the caret.
- A locked paragraph is not typed into: a click or Tab picks it (it takes the focus, ringed, its reason in its title and the status line); the arrows go on from it. A click on the sheet below the last paragraph puts the caret at the end of the last one that may change.

## What was checked?
- `tests/office_document_page_test.php`: `cutLook()` and `exactBase()` in Node, and the source (the typed sheet, Enter and Backspace wired, no Add button).
- Chromium (Playwright) at 1440 and 390 px, a fixture read by `pg_office_read()`: heading, bold run, list, field-locked, tab, a two-paragraph cell, empty and last paragraphs.
- Tried there: split in a bold run, Enter at an end and after a heading, Backspace and Delete joins, the cell, locked and last-paragraph refusals and the fallback, arrows past a locked one, Tab, three-click typing, typing over a locked paragraph (refused), bold over two paragraphs, Ctrl+A underline, Delete, reset.
- The edits were saved by `pg_office_docx_save()` and read back as typed. Not tried on a sandbox's file window or channel preview, nor in Firefox or Safari.

Related: docs/memory/platform/office-docx-undo.md, docs/memory/platform/office-docx-page.md, docs/memory/platform/office-docx-add-delete.md, docs/memory/plans/office-document-page.md
