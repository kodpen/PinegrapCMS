---
name: file-copy-chat-attachments
description: File Manager paste, folder copy, zip archives and the Workspace file picker refuse a team chat attachment to anyone get_file.php refuses it to; an archive holding a form upload stays an attachment
module: file_copy_gate
---

## Which doors ask before copying a file?
- File Manager paste and duplicate (`includes/explorer/handle_files.php`), folder copy (`pg_explorer_copy_folder()`, `view_folder_and_files_f.php`), a zip of a selection (`handle_zip.php`) or of a folder (`pg_explorer_zip_add_folder()`, `archives.php`), and the Workspace "from the files" picker (`ws_library_file()`, `includes/workspace/library.php`).
- Each asks `pg_chat_attachment_refused()` / `pg_chat_attachment_refused_ids()` (`includes/fn/file_copy_gate.php`): a panel chat attachment (`chat_messages.attachment_file_id` of a `backend` conversation) is copied only by a side of a conversation carrying it or a role-0 administrator (`pg_chat_attachment_access_verdict()`, `platform/chat-attachment-access.md`). A single file is refused with "Access denied for …"; in a folder copy or a folder zip it is left out; the picker answers that there is no such file.

## Why?
- A copy, an archive entry or a channel copy is a new file no chat message carries, so `get_file.php` serves it by its folder alone. A manager or designer passes the folder edit check everywhere, so they could paste another staff conversation's attachment, zip it, or post it to a channel, and the copy in the public upload folder opened for anyone (the image editor door was closed the same way in #7149).

## What about submitted-form and comment uploads in a zip?
- A pasted copy keeps `files.attachment` (`tests/explorer_copy_attachment_test.php`); an archive did not. Now an archive gets `attachment = 1` when any form or comment upload went in, so `get_file.php` keeps it to whoever may edit its folder (#1989, `workflow/decisions-made-files.md`).

## Before the chat tables exist?
- The query runs with `@mysqli_query`; when it fails nothing is a chat attachment and nothing is refused.

## Tests?
- `tests/file_copy_gate_test.php`: the pure verdict over rows (`pg_chat_attachment_refused_from_rows()`), and that every door asks before `copy()` / `addFile()`.
