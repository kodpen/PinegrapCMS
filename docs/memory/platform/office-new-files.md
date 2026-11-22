---
name: office-new-files
description: New spreadsheet and New document (OE5, PR #11119): the File Manager's New menu and a channel's attach menu make an empty .xlsx or .docx with the product's writer and open it for editing
module: office_new
---

## Where are they offered?
- File Manager, the New menu under Create File: "New spreadsheet" and "New document" (`data-office-new` xlsx / docx in `view_folders.php`). Shown and greyed exactly as Create File (`newActions().file`); not in the store, the backups or the short links.
- A channel's writing box, the paperclip's menu: the same two items after "Write a note", for whoever may post (`channel.can_post`), not in a discussion or an earlier era, when the office preview is on (`CFG.office`).

## What does each door do?
- File Manager: `explorer_create_office` (folder_id, kind) → `pg_explorer_handle_create_office()` in `includes/explorer/handle_items.php`. Same rule as Create File: a folder the person may edit, outside the recycle bin. Named "New spreadsheet.xlsx" / "New document.docx" in the person's language, numbered by `get_unique_name()`. The screen reloads and opens the file window (`openFileEditor()`), where the name is changed too. Undo bins it. Marks the page cache dirty.
- Channel: `ws_file_office_new` (channel_id, kind) posts the file as the person's message through `ws_message_send_file_bytes()` (the upload checks of any file) and answers the message; the screen opens it in the preview window (`previewFile()`), where Save puts the edited file in its place as for any own file.
- Any other kind: "Only a spreadsheet or a document is made here."

## What is written?
- `includes/fn/office_new.php`: `pg_office_new_name()`, `pg_office_new_write()`, `pg_office_new_bytes()`, `pg_docx_write_blank()`. No library.
- Workbook: the shared writer `pg_xlsx_write_book()`, one sheet "Sheet1" (Turkish "Sayfa1"), a grid A1:J30 (`PG_OFFICE_NEW_ROWS`, `PG_OFFICE_NEW_COLUMNS`) of cells that are there and hold nothing (`pg_xlsx_typed_cell()` with `t` blank gives `<c r="A1"/>`).
- Why the grid: the file window types only into rows a sheet holds (`pg_office_xlsx_patch_sheet()` refuses a missing row) and draws an empty sheet as a note; the head menus add rows and columns past it. The empty cells stay in the file after a save; spreadsheets read them as empty.
- Document: [Content_Types], `_rels`, `word/document.xml` with one empty paragraph and an A4 page (2.5 cm margins), `styles.xml` (Normal, Calibri 11) and `settings.xml` (compatibility mode 15, no protection). Its paragraph is typed into at once; more paragraphs wait for OD3.

## How is it tested?
- `tests/office_new_test.php`: names, the blank cell, both files read back by the preview as editable, a first edit saved and read back (also by `pg_sheet_rows()`), the doors. Checked by hand: openpyxl, python-docx and LibreOffice 24 open both. Not checked: Excel, Word, the screens in a browser.

Related: docs/memory/platform/office-cell-edits.md, docs/memory/platform/office-paragraph-edits.md, docs/memory/workspace/workspace-office-edits.md, docs/memory/plans/office-own-code-steps.md
