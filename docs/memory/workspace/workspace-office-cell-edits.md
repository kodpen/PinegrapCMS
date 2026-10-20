---
name: workspace-office-cell-edits
description: The cells of an Excel file posted in a channel, changed in its preview window (ws_file_office with edit, ws_file_office_save, PR #656): who may, replace or edited copy, written into a copy
---

## How does a workbook of a conversation get edited?
- The preview window (`previewFile()` in `workspace.js`) asks `ws_file_office` with `edit: 1` for an .xlsx/.xlsm/.xltx/.xltm. For someone who may write in the channel (`ws_can_post_channel()`) the answer carries the edit information of the file window (docs/memory/platform/office-cell-edits.md) and `can_replace` / `can_send`, as the text window's `ws_file_text` does; a reader gets the plain preview.
- The grid is the file window's (`pg_office_view.js`); a footer under it says what Save does and holds the button, which stays off until a cell changed (Ctrl+S in the grid presses it). Closing the window with changed cells asks first (`file_cells_unsaved`).
- `ws_file_office_save` (message_id, cells, office_version, as): `ws_file_edit_target()` with kind `office` (an Excel workbook only), then `ws_file_office_edited()` copies the message's file into data/temp, writes the cells into the copy with `pg_office_xlsx_save()` (same locks, same version check: a file replaced meanwhile is refused) and hands back its bytes. The file the message carries is never written to.
- Then the text file's path: `as: replace` (the poster, `ws_file_can_replace()`) puts the new file in place, the old one listed in `ws_file_edits` as an earlier version; otherwise `ws_message_file_send()` posts the edited copy as the person's message in reply. Both go through the upload checks (`ws_upload_inspect()`, 20 MB). The callers pass `fileEdited()`, which redraws the message or syncs the conversation.

## What was checked?
- Sandbox with Playwright: the poster saved over their file (message on the new file id, history row old → new, earlier file untouched); a second staff user got "Send the edited copy", and Ctrl+S posted a reply carrying the copy while the poster's message kept its file; closing with a change asked first.
