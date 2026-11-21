---
name: workspace-file-card-preview
description: A channel file's name opens it on the screen (message file card, Files tab), Preview and Download in words, Save as data for a sheet into a Data table of the channel (#11028)
---

## What does a click on a channel file's name do?
- `fileShowWay(file)` in `workspace.js` decides: `window` (the preview window, `previewFile()`) for a PDF or any file whose payload has `office` (Word, Excel, .ods, .xls, .pptx, .csv/.tsv); `text` (the text window, `openText()`) for another text file (`ws_file_is_text()`); `''` for the rest. Pictures, videos and sounds keep their own players.
- For `window` or `text`, the name is a `<button>` and a click anywhere on the message's file card (`.ws-file-card.ws-file-shows`, its buttons aside) or the Files tab row (`.ws-file-row.ws-file-shows`) opens it; the file's address is never opened.
- A file the channel cannot show keeps its name as the link to the file, in a new tab.
- Before #11028 the name was always that link: a click on a .xlsx opened a tab and downloaded it, and the preview was an eye icon with no word.

## Which buttons does a file carry?
- "Preview" (eye icon) and "Download" (download icon), each `pg-button pg-button-quiet pg-button-sm ws-file-act` built by `fileAction()`, the word in `.pg-btn-label`; under 576 px the word is clipped for a screen reader only (backend.src.css).
- The card keeps "Open the text" only for a .csv/.tsv (its sheet is the Preview); a .txt or .md opens its text window from Preview. The Files tab row keeps the sound's Play and Show in conversation as icons.

## What does "Save as data" do?
- A quiet button in the preview window's head (`.ws-preview-table`, `previewTableButton()`), drawn when `ws_file_office` answers `table_import` (url, channel name, `tables`). The server sends it when `ws_wtable_import_file_offered()`: the file is a sheet (`ws_wtable_import_sheet_name()`: .xlsx, .xls, .ods, .csv, .tsv) and the person may post in the channel and make a table there (`ws_wtable_home_writable()`).
- It opens a line in the window's footer: Into, "A new table in #channel" or a table the person may write (`ws_wtable_import_file_targets()`, at most 50), then Read the sheet.
- Read the sheet posts the Data tables' own `import_read` (`workspace_wtables.php`) with `message` and `sheet` (the tab shown, `previewSheetShown()`) instead of an upload: `ws_wtable_import_message_read()` checks the reader, poster and table rights again and reads the stored file. The import's confirmation and write follow unchanged (`workflow-table-import.md`).
- A new table made this way belongs to the channel (`channel` kept with the read file, passed to `ws_wtable_import_create()`); the confirmation says so.
- The file stays as it is; the table does not follow it. No schema.
- An .xlsx tab is mapped to its worksheet (`ws_wtable_import_worksheet_at()`): the preview lists chart sheets as tabs, the import counts worksheets only; a chart sheet is refused.

## Tests
- `tests/workspace_file_card_preview_test.php` (`fileShowWay()` under node, the pure PHP parts, the drawn markup).

Related: docs/memory/workspace/workspace-channel-files-tab.md, docs/memory/workspace/workflow-table-import.md, docs/memory/platform/office-files.md
