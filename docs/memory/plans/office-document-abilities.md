---
name: office-document-abilities
description: Plan (owner 2026-11-26, poll 235): the document editor's new abilities once it is typed on a page: alignment, text and highlight colour, font size, links, tables, pictures from Files; steps DA1 to DA8
priority: 6
---

## What is asked?
- Owner (2026-11-26, agent channel poll 235, message 14401): all eight abilities offered for the two editors (`workflow/decisions-made-product-115.md`). This plan holds the document's four: format, link, table, picture.

## When does it start?
- After OP3 of the document page (PR #11963; the page `platform/office-docx-page.md`, its Undo `platform/office-docx-undo.md`): every step draws on that page and joins its Undo. Until then no step is free: the architect marks lines `free` when OP3 has landed.

## Which steps? (M about 1,000 product lines, S under 400; each waits for the one before it)
- DA1 (M, `includes/fn/office_docx_format.php`): the format entry also takes a text colour, a highlight (Word's own highlight names) and a font size (8 to 72 points) on selected text, split and joined as bold is; a paragraph entry takes its alignment (start, centre, end, justified). The reader gives them in the marks. Tests as for bold: read back, an untouched paragraph byte for byte.
- DA2 (M, `pg_office_view.js`): the bar gets them, copied from the sheet editor's bar: a `pg-seg` of four alignments, "Text color" and "Highlight color" swatch menus (the sheet's colours; highlight only the ones Word names), a Size select. The page draws each; a control shows the selection's state.
- DA3 (M, new `includes/fn/office_docx_link.php` and the bar): a link on selected text added, changed or taken off: a "Link" `pg-icon-button` (Ctrl+K) opens a small window with the address and "Remove link". Written as a hyperlink with its relationship; only http, https, mailto and tel addresses. A click on a link in the page opens the same window, never the address.
- DA4 (M, new `includes/fn/office_docx_table.php`): entries for a table put in after a paragraph (up to 20 rows by 10 columns, the document's table style when it has one, else thin borders) and for a row or column put in or deleted. A table with merged cells is refused with its reason. Tests: read back, the rest byte for byte.
- DA5 (M): the bar's "Table" button opens a rows by columns grid to choose a size; a cell's paragraphs are typed as any paragraph, Tab goes to the next cell and adds a row after the last. With the caret in a table the bar shows "Insert row", "Insert column", "Delete row", "Delete column", "Delete table".
- DA6 (M, new `includes/fn/office_docx_picture.php`): an entry for a picture put in after a paragraph as its own paragraph: PNG, JPEG or GIF, the part, its relationship and content type written, shown at its own size up to the page's text width. The limits of the preview's pictures hold (`platform/office-files.md`). A paragraph that holds a picture can be deleted.
- DA7 (S): the bar's "Picture" button opens the file window's own picker (in a channel, the channel's files and an upload); the picture is drawn in the page at once.
- DA8 (S) Close-out: `platform/office-docx-format.md` and `office-overview.md` corrected, new `platform/office-docx-links.md`, `office-docx-tables.md` and `office-docx-pictures.md`; this file deleted.

## What does every step keep?
- What the document page keeps (`platform/office-overview.md`): Save a button, no library, no schema, every text through `lang()`, look A.
- A paragraph the editor refuses today stays refused (`platform/office-paragraph-edits.md`); a file the writer cannot carry safely is refused with its reason.
- DA2, DA3, DA5 and DA7 are tried in a browser on a sandbox, in the file window and a channel's preview, at 1440 and 390 px. The PR says what was tried.

Related: docs/memory/platform/office-docx-page.md, docs/memory/platform/office-docx-format.md
