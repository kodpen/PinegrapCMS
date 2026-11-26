---
name: office-docx-page
description: A Word document drawn as a white sheet and typed where it stands (renderDocument(), OP1): no box, the caret where the click fell, the keys, plain paste, the sheet's CSS
---

## How is a document drawn?
- `renderDocument()` in `pg_office_view.js` puts the server's HTML on a sheet (`.office-paper`) inside the scrolling `.office-doc.office-doc-paged`: 816 px wide at most, 72 px top and bottom, 96 px side margins, 24 px and 16 px when the view is under 600 px wide (container query), on the `--pg-bg-2` ground. Read-only documents are drawn the same.
- The sheet carries `data-bs-theme="light"` and `color-scheme: light`, so its `--pg-*` tokens are the light theme's: white with dark text in both themes, as file content (like a mail's body). No colour literal is written.

## How is a paragraph typed in?
- Every paragraph that may change (`.office-para`, not to be deleted) is `contenteditable` (`plaintext-only` where the browser has it, else `true`) with the class `.office-typing`: no box, no ring, `white-space: pre-wrap`. A click puts the caret where it fell; Tab reaches the first paragraph.
- Focus in a paragraph opens the editor state (`shown.editor`: node, position, `typed`, last selection); leaving it keeps what was typed (`keep()`), the window losing the focus does not. Only a paragraph something was typed into is read from its drawing (`paragraphText()`), so focus alone never makes a change.
- An empty paragraph (drawn holding a space) is emptied when the caret comes in. A drawn tab (`.office-tabchar`) is `contenteditable=false`: the caret passes it as one character.
- A changed paragraph is drawn again with its bold, italic and underline carried over (`carryMarks()`); other formatting shows after the save, as before.

## Which keys does the page take?
- Enter: the caret goes to the start of the next paragraph (OP2 makes it split). Shift+Enter: a line break typed by the browser. Tab and Shift+Tab: next and previous paragraph; at either end the focus leaves the document. Esc: what was typed in the paragraph since the caret came in is undone; with nothing typed Esc goes on to the window.
- Ctrl/Cmd+B, I, U act on the selection in the paragraph, kept selected after the redraw (`placeSelection()`); Ctrl/Cmd+S saves. A `format*` input, a drop and an `insertParagraph` (a phone keyboard's Enter) are refused in `beforeinput`.
- Paste takes `text/plain` only, through `insertText`. A link is followed with Ctrl/Cmd and a click (`window.open`, noopener); a plain click places the caret.
- A locked paragraph is not typed into: picked and ringed, its reason in its title and the status line as before; the arrows go on from it. A click on the sheet below the last paragraph puts the caret at the end of the last one that may change.

## What stays as it was?
- The save entries, their order and the refusals (`office-paragraph-edits.md`, `office-docx-format.md`, `office-docx-add-delete.md`); the bar's buttons, which keep the focus in the paragraph on mousedown. Add opens the new paragraph with the caret in it; a paragraph kept again after Delete is typed in again.
- The status hint now reads "Click where you want to type. …"; the string "Text of the paragraph" left the view's strings.

## What was checked?
- `tests/office_document_page_test.php`: `paragraphText()` and `textPoint()` (the inverse of `textOffset()`) in Node over a stand-in DOM, and the source (no textarea, plain paste, the light sheet, the CSS width).
- Chromium (Playwright) on a page built from a fixture read by `pg_office_docx_read()` with a heading, a list, a table, a tab, a field-locked and an empty paragraph, at 1440 and 390 px, light and dark: click and type, Enter, Shift+Enter (also at the end), Esc, Ctrl+B, rich paste, the locked paragraph, Add, Delete and Keep, reset, Ctrl+S, the entries `edits()` gives. Not tried on a sandbox's file window or channel preview, nor in Firefox or Safari.

Related: docs/memory/plans/office-document-page.md, docs/memory/platform/office-paragraph-edits.md
