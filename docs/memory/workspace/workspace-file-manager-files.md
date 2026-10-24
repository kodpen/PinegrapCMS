---
name: workspace-file-manager-files
description: A file of the file manager posted into a Workspace channel as a copy (library.php): "Send to a channel" in the file manager, "From the file manager" under the paperclip; who may pick what, refusals
---

## How does a file of the file manager reach a channel?
- Two doors, one path (PR #564): the file manager's item menu "Send to a channel" (`view_folders.php`, window `#ws_send_modal`, channels from `ws_library_channels`) and the writing box's paperclip, which now opens "From this device" / "From the file manager" (`libraryPicker()` in `workspace.js`, offcanvas `#ws-library`). Both post through `ws_library_attach` → `ws_library_send()` → `ws_message_send_file_bytes()`, the upload's own path: same refusals (`ws_upload_inspect()`), same ceiling (`ws_upload_max_bytes()`, measured with `filesize()` before the file is read), the message checked before the file is kept, the copy taken back when the message fails.
- The channel gets a copy in its own folder (a discussion's in its channel's), named like an upload; the message carries the original's name. The original is not tagged in the message: a chip with the same name beside the file card read as a second file, and the copy is what the channel's readers may open.

## Who may pick which file?
- What the file manager shows them (`ws_library_granted()`, then `ws_library_open()`): staff every folder, a user (role 3) the folders they may edit and those below (`get_folders_that_user_has_access_to()`).
- Closed for everybody, each with everything below it: the Workspace folder (`config.ws_folder_id`; its files belong to the conversation they were posted in, and a forward carries only a file's name), the recycle bin, archived folders. Files without a folder (ERP documents) never come up; `pg_files_include_erp_document()` is asked again by id.
- A file in the Workspace folder is refused with "already in the Workspace, forward the message" to whoever the file manager shows it to (`ws_library_granted()`), anybody else hears there is no such file; the file manager disables the entry there (`insideWorkspaceFolder()`), offers it only to Workspace members (`ws_rights()['member']`) and never in the store area.
- Channels offered: `ws_channels_for()` kept to `ws_can_post_channel()`; the send checks again.

## What does the picker list?
- `ws_library_browse()`: a folder's subfolders (file manager order) and its first 200 files by name; a search (name contains, newest first, 50); at the start the top folder (staff) or each grant's top side by side (`several`), plus the 12 files changed last. A file a channel would refuse (`workspace-upload-refusals.md`) stays listed, greyed, with the reason. Pictures show their own address as a lazy thumbnail.
- Pure helpers, tested in `tests/workspace_library_test.php`: `ws_library_branch()`, `ws_library_open_set()`, `ws_library_tops()`, `ws_library_trail()`.

## What is not there?
- No external API route of its own: an app reads `GET /files/{id}` and posts with `POST /workspace/channels/{id}/files`. One file at a time from the file manager (a multiple selection disables the entry).
