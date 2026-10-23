---
name: workspace-upload-refusals
description: What a Workspace channel refuses to take (ws_upload_refused()): the file manager's list, page and script types, Office files with macros (PR #TBD); why, every door it covers, the message
---

## What does a channel refuse?
- `ws_upload_refused()` (includes/workspace/files.php): whatever the file manager refuses (`pg_upload_name_blocked()`: PHP, server config, CGI, Windows programs), plus `ws_upload_refused_extensions()`: svg, svgz, html, htm, xhtml, xht, xml, js, mjs, swf (a browser would run them as a page of this site) and `ws_upload_macro_extensions()`: docm, dotm, xlsm, xltm, xlam, pptm, potm, ppsm, ppam, sldm (PR #TBD).
- Every part of the name after the first is matched, case-insensitive: `q3.xlsm.zip` is refused too. One list, no JavaScript copy; the browser checks only the size.
- The parts come from the name as sent and as `prepare_file_name()` stores it (`ws_upload_name_parts()`), for the page and script types too: `q3.xlsm!` is stored as `q3.xlsm`, so it is refused (the `dkim.key` rule, `platform/dkim-key-in-file-manager.md`). `pg_upload_name_blocked()` asks the name as sent only: `отчет.xlsx` is stored as `file.xlsx`, not a dotfile.

## Why the macro forms, and why not .doc, .xls, .ppt or .xlsb?
- Office runs a file's macros once the reader enables content, and a channel is where many people open files from, so it does not pass them on.
- The legacy binary forms and .xlsb can carry macros too, but they are everyday files and nothing in their name tells; refusing them would be a separate owner decision.
- The file manager itself still takes macro files: only channels refuse them.

## Which doors does it cover?
- All end in `ws_upload_refused()`:
  - the panel's paperclip, drop and paste (`ws_attach` → `ws_message_send_file()` → `ws_upload_inspect()`);
  - `POST /workspace/channels/{id}/files`, its dry run included (`ws_upload_check()` runs before `api_dry_run_stop()`; 422);
  - the file manager's "Send to a channel" (`ws_library_send()` → `ws_message_send_file_bytes()`);
  - the picker "From the file manager", which lists the file greyed with "Channels do not take this kind of file." (`ws_library_file_payload()`);
  - note attachments (`ws_note_file_store_bytes()`);
  - the notes import, which counts such a file as missing and leaves its link as a plain label (`ws_note_import_count_files()`);
  - edit saves: `ws_file_office_save`, `ws_file_text_save` and the image editor's `workspace_file_save.php`, replace (`ws_message_file_replace()` → `ws_store_upload()`) or copy (`ws_message_file_send()`); an office or text save keeps the file's own name, a picture takes its new extension (`ws_file_edit_name()`).
- A discussion copy (`ws_thread_copy_sync()`) reuses the file id and checks nothing; a forward carries only the name; guests add no files; the files route is `'mcp' => false`, so MCP posts none.

## What does the person read?
- `ws_upload_refused_message()`: a macro name gets "Sorry, {var:1} was not accepted. Office files with macros cannot be added here; save it without macros (.docx, .xlsx or .pptx) and try again."; any other refused name, and one the file manager refuses too (`shell.php.docm`), the file manager's `pg_upload_blocked_message()`. Used by `ws_upload_inspect()`, note attachments and `ws_file_edit_target()`.

## What happens to a macro file posted before?
- It stays and opens read only: `ws_file_office` offers no editing for a name `ws_upload_refused()` refuses (no `can_replace` / `can_send`, so the preview has no Save), and `ws_file_edit_target()` with kind `office` answers a save from a window opened earlier with the sentence above, before anything is rewritten. Download and the preview still work.

## Where are the tests?
- `tests/workspace_upload_refusals_test.php` (the ten forms, macro-free forms taken, the stored name, the message, the source checks); `test_ws_upload_inspect` in `tests/workspace_api_test.php`.
