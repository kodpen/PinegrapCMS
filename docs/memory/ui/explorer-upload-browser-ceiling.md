---
name: explorer-upload-browser-ceiling
description: File Manager upload window limit capped at what a browser can send as one data URL (about 383 MB, pg_explorer_upload_max_bytes()); a file FileReader reads as nothing is named and not sent
---

## Why is the File Manager's upload limit not the server's figure?
- The upload window (New > Upload files, a drop, the backup folder) reads each file with `readAsDataURL()` and sends it inside one JSON request. A JavaScript string is at most 2^29 - 24 characters in Chrome and Edge (V8); Firefox and Safari take more. Base64 writes 4 characters for 3 bytes.
- `pg_explorer_upload_max_bytes($json_max)` (`includes/explorer/handle_upload.php`) is the smaller of the server's JSON ceiling (`pg_upload_limits()['json_max']`, `platform/upload-limits-and-empty-post.md`) and 401,866,734 bytes (that string less 1 MB for the rest of the request), written "383.2 MB". A `json_max` of 0 gives the browser figure alone.
- The folder and flat listings (`handle_list.php`) and the backup listing (`handle_backups.php`) send it as `upload_max_bytes` / `upload_max_label`, so the window's "Up to … per file", the over-limit mark in the list and the too-large toast all use it. The server's own check in `pg_explorer_handle_upload()` still reads `json_max`.

## What happens to a file the browser cannot read?
- `uploadReadUsable(reader.result)` is false for an empty or non data URL result. The file is not sent and a toast says "{file}: The browser could not read the file, so it was not sent." (`L.upload_read_failed`); a FileReader error says the same. Both the folder path (`uploadEntries()`) and the backup path (`uploadToBackups()`) check it.
- Before (#8989): a 420 MB file under the stated 1.1 GB was read as `""` with no error, sent with `"data":""` and answered by a bare "Invalid request." naming no file.

## What is left?
- Files larger than the ceiling need the upload in parts the image editor uses (`ui/image-editor-upload.md`); the File Manager has no such path yet.
- The Workspace's file uploads read their own ceiling (`includes/workspace/files.php`) and were not looked at.

Related: tests/explorer_upload_notices_test.php
