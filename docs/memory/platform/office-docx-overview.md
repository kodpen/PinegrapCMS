---
name: office-docx-overview
description: Start file for the office-docx-* files: the Word document typed on its page in the file window and a channel's preview, by what a person sees, and which file holds each part
---

## Where does the Word editor start?
- The whole Office view (Word and Excel, previews, decisions): `office-overview.md`. This file lists only the Word editor's parts.

## Which file holds which part, by what a person sees?
- The page typed where it stands: `office-docx-page.md`; its keys (Enter, Backspace, arrows, Tab, paste, a click on a link): `office-docx-page-keys.md`; Undo and Redo: `office-docx-undo.md`.
- Paragraph text saved and what is never changed: `office-paragraph-edits.md`. A paragraph added (Enter) or deleted (Delete the paragraph): `office-docx-add-delete.md`.
- The bar: Bold, Italic, Underline `office-docx-format.md`; Size, Text color, Highlight color and the alignments `office-docx-bar-look.md` over `office-docx-format-look.md`; Paragraph style `office-docx-paragraph-kind.md`; Link and its window `office-docx-links.md`; Table, its size grid, rows and columns `office-docx-table-bar.md` over `office-docx-tables.md`; Picture `office-docx-picture-bar.md` over `office-docx-pictures.md`.
- The preview's reading of content controls, numbering and run properties turned off: `office-docx-controls-numbering.md`.

## How is a new Word ability added?
- As `office-overview.md` says under "How is a new part added?": a save entry (or a format stretch) in its own writer module with its test, one change of Undo, the bar's parts copied from look A.
- A paragraph the editor refuses stays refused (`office-paragraph-edits.md`); the page refuses what the writer would, with the same limits (tested equal), and the writer still refuses on save.
- Bar controls are tried in a browser on a sandbox, in the file window and a channel's preview, at 1440 and 390 px; the PR says what was tried.
