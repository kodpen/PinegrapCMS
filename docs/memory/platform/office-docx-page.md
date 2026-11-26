---
name: office-docx-page
description: A Word document drawn as a white sheet and typed where it stands (renderDocument(), OP1, OP2 PR #11940): one typed sheet, the caret followed, what stays; the keys in office-docx-page-keys.md
---

## How is a document drawn?
- `renderDocument()` in `pg_office_view.js` puts the server's HTML on a sheet (`.office-paper`) inside the scrolling `.office-doc.office-doc-paged`: 816 px wide at most, 72 px top and bottom, 96 px side margins, 24 px and 16 px when the view is under 600 px wide (container query), on the `--pg-bg-2` ground. Read-only documents are drawn the same.
- The sheet carries `data-bs-theme="light"` and `color-scheme: light`, so its `--pg-*` tokens are the light theme's: white with dark text in both themes, as file content (like a mail's body). No colour literal is written.

## How is a paragraph typed in?
- The sheet (`.office-paper.office-sheet-typing`) is the one `contenteditable` (`plaintext-only` where the browser has it, else `true`), so a selection and the arrows cross paragraphs (PR #11940: with one editing host a paragraph, Chromium kept a drag and Shift+arrows inside one). Paragraphs that may change (`.office-typing`) have no attribute of their own; locked and to-be-deleted ones, a drawn tab and top-level parts with no paragraph are `contenteditable=false`.
- `follow()` (on `selectionchange`, focus and every key) makes the paragraph holding the caret the one typed in (`shown.editor`); a selection over several leaves it as it is. A caret the browser leaves outside every typed paragraph (a table's edge, a locked one) goes on to the next one in the arrow's way. Leaving a paragraph keeps what was typed (`keep()`); only one typed into is read from its drawing (`paragraphText()`).
- An empty paragraph holds a `br` while typed in (an empty block holds no caret in Chromium). A changed paragraph is drawn again with its bold, italic and underline carried over (`carryMarks()`).

## Which keys does the page take?
- Enter splits, Backspace joins, the arrows and a selection cross paragraphs: `office-docx-page-keys.md`.

## What stays as it was?
- The save entries, their order and the refusals (`office-paragraph-edits.md`, `office-docx-format.md`, `office-docx-add-delete.md`): a split is a text entry and an `{after}` entry, a join a text entry and a `{delete}` entry. The bar keeps Bold, Italic, Underline, Paragraph style and "Delete the paragraph"; "Add a paragraph after this one" left it (Enter adds one).
- The status hint reads "Click where you want to type. Enter starts a new paragraph, Backspace at its start joins it to the one before, …".

## What was checked?
- `tests/office_document_page_test.php`: `paragraphText()`, `textPoint()` (the inverse of `textOffset()`), `cutLook()` and `exactBase()` in Node, and the source (no textarea, plain paste, the light sheet, the typed sheet, no Add button, the CSS width).
- Chromium (Playwright) on a page built from a fixture read by `pg_office_docx_read()` with a heading, a list, a table, a tab, a field-locked and an empty paragraph, at 1440 and 390 px, light and dark: click and type, Enter (then: to the next paragraph), Shift+Enter (also at the end), Esc, Ctrl+B, rich paste, the locked paragraph, Add, Delete and Keep, reset, Ctrl+S, the entries `edits()` gives. Not tried on a sandbox's file window or channel preview, nor in Firefox or Safari.

Related: docs/memory/platform/office-docx-page-keys.md, docs/memory/platform/office-paragraph-edits.md
