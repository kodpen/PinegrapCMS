---
name: workspace-notes-import-upload
description: The notes import's upload: picked list, checks, XHR progress, cancel, reading step, focus, footer gate, leave guard, request_max cap, per-file sentences, session closed before staging
---

## What does the person see from picking to the preview?
- Picking or dropping only lists the files (`drawPick()` in `openImport()`, workspace.js), as the file manager does: name, size, a count heading, rows that will not go dimmed with "not allowed here" or their size in red. "Upload" sends, "Clear" empties.
- One row a name, the newer pick wins: a file changed on disk after picking fails every request (ERR_UPLOAD_FILE_CHANGED); picking it again replaces it (added beside it, the stale file still went).
- Sending: "Uploading… 42%", the shared `progressBar()`, "1.4 MB of 6.0 MB", "Cancel upload". Last byte sent: "Reading the files…" with a striped, moving bar, then the preview. `.ws-progress` sets only `background-color`: the shorthand hid the stripes.
- A refused upload returns to the list with its message (`role="alert"`). Keys: `notes_import_*` (`ws_notes_js_strings()`), `uploading`, `percent`.

## Why XMLHttpRequest for the upload step only?
- fetch() reports no upload progress; the JSON steps keep `send()`. A lost connection says "The request failed. Please try again."
- An HTML 413/404 (nginx, IIS 404.13) says "The upload is larger than this server accepts in one request."; other HTML (504) the generic text.
- Late upload events after a cancel or the answer are ignored (`phase === 'send'`).

## What does the browser check, and from which list?
- The kinds: `CFG.notes_import.types` = `ws_note_import_extensions()`, the stage's list; no copy in JS.
- A folder, another kind or a file over the cap is left out, the rest sent. Dropped folders are found by `webkitGetAsEntry()` (a folder "Vault.md" passes by name).
- Over the files that go: none left, more than `limits.files` (max_file_uploads; only the screen can check it), then the stage's order: over `limits.max`, a zip with others, .enex with others. One file is held to the cap by its size; several add 1 KB of part headers each.

## Where does the focus go; why can a second tap not stop the upload?
- Upload, then Cancel upload, Close, the preview's heading (not Import), the result. Footer clicks are dropped for 800 ms after Upload, repeated Enter/Space keydowns always: a double tap stopped the upload or imported unseen.

## Why is the cap min(100 MB, file_max, request_max)?
- Loose files go in one request. file_max (clamped to post_max_size) could equal post_max_size: a total in its last 64 KB (the multipart envelope) passed the screen and failed as a 413. No batches: a stage is all-or-nothing (the exception to platform/upload-limits-and-empty-post.md).

## What do Cancel, X, Escape and leaving do?
- Escape never closes it. Sending: Cancel aborts back to the list; X aborts and closes. Reading: Cancel or X closes, the stage dropped when the answer comes. Writing: no closing.
- A `beforeunload` guard stands only while sending, reading or writing (a standing one blocks the back-forward cache).

## What does the server say about a file it did not get whole?
- `ws_note_import_upload_error($name, $error, $file_max)`: add_file.php's sentences naming the file. No Site Log line; PARTIAL (a cancel) or a size code is not logged; codes 6-8 and a non-upload temp file go to `ws_note_import_log_failure()`. UPLOAD_ERR_NO_FILE reads as an empty upload, unlogged.

## Why is the session closed before staging?
- Staging .enex or a zip can pass 30 s, and the session lock held the person's other requests. The endpoint makes the token, keeps it in the session, `session_write_close()`, raises the time limit (guarded), then stages with that token. A failed stage leaves a name with no folder: later steps say "The upload has expired. Start again."
- Two uploads at once in one session: the newer removes the older's folder; the older fails ("The file could not be saved.", logged) or its later steps say it expired and its stage waits for the 24 h sweep.
