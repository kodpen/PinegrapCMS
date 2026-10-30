---
name: workspace-attachment-uploads
description: How the Workspace screen sends channel and note attachments: a form part over XMLHttpRequest with a progress card and Cancel (#3745), the shared server check, base64 still taken, limits
---

## How does a channel or note attachment travel? (PR #NNNN, decision #3745)
- `apiUpload(action, fields, file, name, onProgress)` in workspace.js: a `FormData` (action, token, the fields, the file as part `file`) posted to `CFG.api_url` over XMLHttpRequest, because fetch() reports no upload progress. Answer read as `api()` reads it; nginx 413 / IIS 404.13 read as "larger than this server accepts in one request".
- Both doors use it: the channel's paperclip, drop, paste and voice (`uploadFiles()` → `ws_attach`) and a note's drop, picker and recording (`attach()` → `ws_note_file`). No FileReader, no base64: no third more on the wire, no JSON memory ceiling.
- No list of picked files first (the notes import's `drawPick()` stays the import's): one attachment is one step.

## What does the person see, and what does Cancel do?
- `uploadCard()`: a card in the notice corner with "Uploading…" (or "Uploading 2 of 5…"), the file's name, the shared `progressBar()`, "1.4 MB of 6.0 MB" (`sizeLabel()`, module level) and "Cancel upload". A browser without figures gets the striped, moving bar.
- Cancel aborts the request ("Upload cancelled."). In a channel it also drops the files still queued behind it; files already posted stay. A note's files go side by side, one card each.
- While any card stands a `beforeunload` guard asks before leaving; it is removed with the last card (back-forward cache).

## How does the server read it?
- `api.php`: a `multipart/form-data` body's `$_POST` is the request (token, action). An empty one (over post_max_size) gets the same "larger than this server accepts" answer as an empty JSON body.
- `ws_upload_request_file($request)` (files.php): the part `file` when sent, else base64 `data` (older screens, scripts: kept working). A part goes through `ws_upload_part_error()` - the same check `ws_api_upload_input()` uses for `POST /workspace/channels/{id}/files` - against `ws_upload_form_max_bytes()` = min(`ws_upload_max_bytes()`, upload_max_filesize).
- Then the bytes go where base64 went: `ws_message_send_file_bytes()` / `ws_note_file_store_bytes()`: same type refusals (`workspace-upload-refusals.md`), same folder, same rows. `BOOT.upload.max` is the form limit.

## Where are the tests?
- `tests/workspace_attachment_upload_test.php`: the part check, base64 still read, both receivers on the shared reader, the API on the same check, both JS doors on `apiUpload()` with progress and abort and no `readAsDataURL`.
